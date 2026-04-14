# Future Feature — Multi-Category Product Listings

## Problem

Currently `Product` has a single `category_id` (BelongsTo). Every listing for a product
inherits that one category. You cannot place TSHIRT-001 in both "Apparel" and "Summer Sale".

## Two Architectural Options

### Option A — Many-to-many on Product (product belongs to multiple categories)

Product itself can live in N categories. All its listings inherit all those categories.

```
products ──< product_category_pivot >── product_categories
```

- Add `product_category` pivot table: `product_id`, `category_id`
- Drop `category_id` from `products` (or keep as "primary category")
- `Product::categories()` — BelongsToMany
- `Product::primaryCategory()` — BelongsTo (optional convenience)
- Views show comma-separated category names

**Best when:** the product fundamentally *is* in multiple categories regardless of how it's listed.

---

### Option B — Category at listing level (each listing can be in a different category)

`ProductListing` gets its own `category_id`. Same SKU, different listings, different categories.
TSHIRT-001/Blue-M → Apparel. TSHIRT-001/Blue-M → Summer Sale. Independent listings.

```
product_listings.category_id → product_categories.id
```

- Add `category_id` (nullable FK) to `product_listings`
- `ProductListing::category()` — BelongsTo ProductCategory
- `Product::category_id` stays (or is removed — decision needed)
- `StoreProductListingRequest` adds `category_id` rule
- `UpdateProductListingRequest` adds `category_id` rule (mutable, unlike product_id)

**Best when:** the listing is the presentation unit and a product can be marketed differently
in different catalog contexts (e.g. same widget listed under "Tools" and "Gifts").

---

## Recommendation

**Option B fits this module's design better.** `ProductListing` is already the
"how this product appears in the catalog" layer. Moving category to the listing
makes each listing fully self-describing and independent.

If a product almost always only has one category, make `category_id` nullable on
`product_listings` and fall back to `$listing->product->category` for display.

---

## Implementation Checklist (Option B)

### Migration
- [ ] `add_category_id_to_product_listings_table` — nullable FK to `product_categories`
- [ ] Index on `product_listings.category_id`

### Model
- [ ] `ProductListing::category()` — BelongsTo ProductCategory (nullable)
- [ ] Add `category_id` to `$fillable`
- [ ] Eager-load: replace `product.category` with direct `category` in service

### Service
- [ ] `list()` — add `category` to with(); add `category_id` filter
- [ ] `create()` / `update()` — pass `category_id` through (no special handling needed)
- [ ] Drop `product.category` from eager loads (no longer needed for display)

### Controller
- [ ] Add `category_id` filter to `index()` — `$request->only([..., 'category_id'])`
- [ ] Pass `$categories` dropdown to `index()`, `create()`, `edit()`

### Requests
- [ ] `StoreProductListingRequest` — add `'category_id' => ['nullable', 'integer', 'exists:product_categories,id']`
- [ ] `UpdateProductListingRequest` — same (mutable unlike product_id)

### Views
- [ ] `index.blade.php` — add category filter dropdown; show `$listing->category?->name ?? $listing->product->category?->name`
- [ ] `show.blade.php` — show listing's own category (fallback to product category if null)
- [ ] `_form.blade.php` — add category select on both create and edit

### Seeder
- [ ] `ProductListingSeeder` — pass `category_id` when creating listings

### Tests
- [ ] Feature: filtering by `category_id`
- [ ] Feature: create/update with category
- [ ] Unit: service list() filter

---

## Notes
- If keeping `Product.category_id` alongside `ProductListing.category_id`, document the
  precedence rule clearly: listing category takes priority; fall back to product category.
- If removing `Product.category_id`, that's a breaking change to the Product module — plan
  a separate migration and update ProductController/views/tests accordingly.
