# Product Slug Feature — Service

## Dependencies

- **Requires:** `02-model.md` (HasSlug on ProductListing, ProductListingSlugRedirect model)
- **Modifies:** `app/Services/ProductListingService.php` — slug-related methods only
- Full service with all methods is in `product-list/03-service.md`

---

## create() — Slug on First Save

The slug closure reads `$listing->product->sku`. On a new listing, the product relation
isn't loaded from the DB yet (no `id`). We must inject it manually with `setRelation()`
before `save()` so sluggable can read `product->sku` without an extra query.

```php
public function create(array $data): ProductListing
{
    return DB::transaction(function () use ($data): ProductListing {
        // Fetch product so sluggable closure can read product->sku
        $product = Product::findOrFail($data['product_id']);

        $listing = new ProductListing($data);
        $listing->setRelation('product', $product); // ← CRITICAL: must be before save()
        $listing->save();                           // ← sluggable fires here

        return $listing->load('product:id,sku,name,regular_price,sale_price');
    });
}
```

### Why setRelation() and not load()?

`load()` hits the database and requires the model to have an `id`. On a new unsaved listing
there is no `id` yet — `load()` would fail. `setRelation()` injects the already-fetched
model directly — zero extra queries, works before save.

---

## update() — Slug Regen + Redirect on Title Change

`doNotGenerateSlugsOnUpdate()` is set, so sluggable won't regen automatically on save.
The service checks if the title changed, then:
1. Captures the old slug before it's overwritten
2. Fills new data and calls `generateSlug()` manually
3. Saves (writes new slug to DB)
4. Saves old slug to redirects via `firstOrCreate()`

```php
public function update(ProductListing $listing, array $data): ProductListing
{
    return DB::transaction(function () use ($listing, $data): ProductListing {
        unset($data['product_id']); // immutable — strip even if submitted

        $titleChanged = isset($data['title']) && $data['title'] !== $listing->title;

        if ($titleChanged) {
            $oldSlug = $listing->slug; // ← capture BEFORE regen overwrites it

            $listing->fill($data);
            $listing->generateSlug(); // ← forces regen despite doNotGenerateSlugsOnUpdate
            $listing->save();         // ← writes new slug to DB

            // firstOrCreate is idempotent — same title set twice won't create duplicates
            ProductListingSlugRedirect::firstOrCreate(
                ['old_slug' => $oldSlug],
                ['listing_id' => $listing->id],
            );
        } else {
            $listing->update($data); // no slug change needed
        }

        return $listing->fresh('product:id,sku,name,regular_price,sale_price');
    });
}
```

---

## regenerateSlugsForProduct() — Bulk Regen on SKU Change

Called by `ProductService::update()` when a product's SKU changes. Loops all non-trashed
listings and regenerates each slug, creating a redirect record for each changed slug.

```php
/**
 * Regenerate slugs for all active (non-trashed) listings when a product's SKU changes.
 *
 * WHY TRASHED LISTINGS ARE SKIPPED:
 *   1. Trashed listings are not served on the storefront — a stale slug on a trashed
 *      listing causes zero live breakage. No real URLs point to them.
 *   2. Creating redirect records for trashed listings adds noise to the redirects table:
 *      the portal route would find the redirect, then find $redirect->listing = null
 *      (soft-deleted), and fall through to 404 anyway — the redirect is pointless.
 *   3. If a trashed listing is restored after a SKU change, its slug will carry the
 *      old SKU prefix. This is acceptable — the admin should update the listing title
 *      after restore, which triggers a fresh slug regen + redirect automatically.
 *
 * Called inside ProductService::update() transaction — if regen fails,
 * the SKU update rolls back too. Product SKU and listing slugs never go out of sync.
 */
public function regenerateSlugsForProduct(Product $product): void
{
    // Default scope excludes trashed — intentional, see above.
    $listings = ProductListing::where('product_id', $product->id)->get();

    foreach ($listings as $listing) {
        $oldSlug = $listing->slug;

        // Inject the already-fetched product (with new SKU) so sluggable closure
        // reads the updated SKU. setRelation() avoids a DB query — same pattern
        // as create().
        $listing->setRelation('product', $product);
        $listing->generateSlug();
        $listing->save();

        // Only create a redirect if the slug actually changed.
        // Edge case: new SKU may slugify to the same prefix — no redirect needed.
        if ($oldSlug !== $listing->slug) {
            ProductListingSlugRedirect::firstOrCreate(
                ['old_slug'   => $oldSlug],
                ['listing_id' => $listing->id],
            );
        }
    }
}
```

---

## Slug Flow Diagrams

```
CREATE
  new ProductListing($data)
  setRelation('product', $product)       ← inject before save, no DB hit
  save()
    └── sluggable closure fires
        └── reads product->sku + title
        └── slugifies → "tshirt-001-blue-m"
        └── uniqueness check → suffix -2 if collision
        └── writes to slug column

UPDATE (title changed)
  $oldSlug = "tshirt-001-blue-m"        ← captured BEFORE regen
  fill(['title' => 'Blue / Medium'])
  generateSlug()  → new slug: "tshirt-001-blue-medium"
  save()
  firstOrCreate(['old_slug' => 'tshirt-001-blue-m'], ['listing_id' => $id])

UPDATE (title NOT changed)
  $listing->update($data)               ← no slug work at all

SKU CHANGE (called from ProductService::update())
  regenerateSlugsForProduct($product->fresh())
    └── loops non-trashed listings only
        each listing:
          $oldSlug = "tshirt-001-blue-m"
          setRelation('product', $product)  ← new SKU injected
          generateSlug() → "tshirt-xl-001-blue-m"
          save()
          firstOrCreate if slug changed
```

---

## Checklist

- [ ] `create()` calls `Product::findOrFail()` before constructing the listing
- [ ] `create()` calls `setRelation('product', $product)` before `save()`
- [ ] `update()` strips `product_id` with `unset()`
- [ ] `update()` captures `$oldSlug` BEFORE calling `generateSlug()`
- [ ] `update()` calls `generateSlug()` then `save()` — not just `update()`
- [ ] `update()` uses `firstOrCreate()` — NOT `create()` — for the redirect record
- [ ] `regenerateSlugsForProduct()` queries non-trashed listings only (default scope)
- [ ] `regenerateSlugsForProduct()` uses `setRelation()` to inject updated product
- [ ] `regenerateSlugsForProduct()` only creates redirect if slug actually changed
- [ ] All slug writes wrapped in `DB::transaction()`
