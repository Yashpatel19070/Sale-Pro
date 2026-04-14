# ProductList Module — Views

## View Files
```
resources/views/product_listings/
├── index.blade.php
├── show.blade.php
├── create.blade.php
├── edit.blade.php
└── _form.blade.php
```

All views extend `layouts.admin` and use Tailwind CSS v3.

---

## index.blade.php

**Purpose:** All listings, filterable. Primary admin management page.

**Sections:**
- Header: "Product Listings" + "New Listing" button (if can create)
- Filter bar:
  - Search input (title) — `?search=`
  - Product dropdown — `?product_id=` (pre-selected if coming from product show page)
  - Visibility select — `?visibility=`
  - Active filter — `?active=1|0`
- Table columns: Title | SKU | Product | Category | Regular Price | Sale Price | Visibility | Status | Actions
  - SKU: monospaced, from `$listing->product->sku`
  - Product: `$listing->product->name` (link to `products.show`)
  - Category: `$listing->product->category?->name` (null-safe, shows `—` if unset)
  - Regular Price + Sale Price: read from `$listing->product` (no price on listing itself)
  - Visibility: badge via `$listing->visibility->badgeClass()` + `->label()`
  - Status: green/gray badge
  - Actions: View, Edit, Toggle Visibility, Delete
- Empty state
- Pagination

---

## show.blade.php

**Purpose:** Listing detail.

**Sections:**
- Back link: to index (or parent product if `?from=product`)
- Header: listing title + visibility badge + status badge
- Details grid:
  - Product name (link to `products.show`)
  - SKU (monospaced, from `$listing->product->sku`)
  - Category (`$listing->product->category?->name`)
  - Slug (read-only)
  - Visibility
  - Status
  - Prices (from parent product):
    - Regular Price: `$listing->product->regular_price`
    - Sale Price: `$listing->product->sale_price` (shown only if set)
- Edit + Delete buttons (gated by policy)
- Timestamps

---

## create.blade.php / edit.blade.php

```blade
{{-- create.blade.php --}}
@extends('layouts.admin')
@section('content')
    <x-page-header title="New Listing" :back="route('product-listings.index')" />
    @include('product_listings._form', ['listing' => null])
@endsection

{{-- edit.blade.php --}}
@extends('layouts.admin')
@section('content')
    <x-page-header title="Edit Listing" :back="route('product-listings.show', $listing)" />
    @include('product_listings._form', ['listing' => $listing])
@endsection
```

---

## _form.blade.php

**Fields:**

| Field | Type | Notes |
|-------|------|-------|
| Product | select | From `$products` dropdown — create only; on edit show as readonly text |
| Title | text | required; human label for this listing |
| Visibility | select | from `$visibilities` (ListingVisibility::options()) |
| Active | checkbox | |

**Product context panel (read-only, shown on edit):**
- Line 1: `SKU: TSHIRT-001  ·  Category: Apparel`
- Line 2: `Regular: $14.99  ·  Sale: $9.99  (prices managed on the product)`
- On create: JS price-info box appears after product select (shows regular + sale price only — no category yet since dropdown doesn't carry it)

**Form action:**
- Create: `POST /admin/product-listings`
- Edit: `PATCH /admin/product-listings/{productListing}`

---

## Key UI Decisions

### Product immutable on edit
On the edit form, show parent product as read-only text (not a select).
Do not pass `product_id` back to the controller.

### All product context is read-only on listing views
Listings carry NO own price, SKU, or category fields. Everything is read from `$listing->product`.
Index, show, and edit views all display SKU + Category + prices from the eager-loaded product.

### Visibility badges
Use `$listing->visibility->badgeClass()` + `->label()` — do NOT inline `@if/@elseif/@else` blocks.

### Slug
Auto-generated — never shown or editable in the form. Shown read-only on the show page.

### One SKU → one category (current design)
`Product` has a single `category_id` (BelongsTo). All listings for a product inherit that same category.
If multi-category is ever needed, the decision point is: add a pivot on `Product ↔ ProductCategory`
(product appears in multiple categories) OR move `category_id` to `ProductListing` (each listing
can be in a different category). See architectural note in `00-overview.md`.

## Checklist
- [x] index: filter by product, visibility, active, search
- [x] index: SKU column (monospaced), Product name, Category, prices — all from eager-loaded product
- [x] show: SKU, Category, slug, prices displayed as read-only from product
- [x] _form: product select on create only; read-only panel (SKU + category + prices) on edit
- [x] _form: no price, stock, or attribute inputs
- [x] All forms use `@csrf` + correct `@method` for PATCH
- [x] Validation errors shown with `x-input-error` on each field
