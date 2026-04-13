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
- Table columns: Title | Product | Regular Price | Sale Price | Visibility | Status | Actions
  - Regular Price + Sale Price: read from `$listing->product` (no price on listing itself)
  - Visibility: badge (green=public, yellow=private, gray=draft)
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
  - Parent product (link to `products.show`)
  - Slug (read-only)
  - Visibility
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

**Price info block (read-only, shown on create + edit):**
- On create: after selecting a product via JS, show the product's `regular_price` and `sale_price` as a read-only info box — "This listing will display prices from the selected product."
- On edit: show the parent product's current prices as read-only reference panel.

**Form action:**
- Create: `POST /admin/product-listings`
- Edit: `PATCH /admin/product-listings/{productListing}`

---

## Key UI Decisions

### Product immutable on edit
On the edit form, show parent product as read-only text (not a select).
Do not pass `product_id` back to the controller.

### Prices are read-only on listing form
Listing forms have no price inputs. Prices are always inherited from the parent product.
Show a read-only info panel: "Prices are managed on the product. Regular: $X.XX / Sale: $X.XX"

### Slug
Auto-generated — never shown or editable in the form. Shown read-only on the show page.

## Checklist
- [ ] index: filter by product, visibility, active, search
- [ ] index: prices from `$listing->product` (eager loaded)
- [ ] show: prices displayed as read-only from product
- [ ] show: slug displayed as read-only
- [ ] _form: product select on create only; text on edit
- [ ] _form: no price, stock, or attribute inputs
- [ ] _form: read-only price info panel showing product's prices
- [ ] All forms use `@csrf` + correct `@method` for PATCH
- [ ] Validation errors shown with `@error` on each field
