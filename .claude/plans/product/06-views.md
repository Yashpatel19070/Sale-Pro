# Product Module — Views

## View Files
```
resources/views/products/
├── index.blade.php
├── show.blade.php
├── create.blade.php
├── edit.blade.php
└── _form.blade.php
```

All views extend `layouts.admin` and use Tailwind CSS v3.

---

## index.blade.php

**Purpose:** Paginated product list with filters.

**Sections:**
- Page header: "Products" + "New Product" button (if `can('create', App\Models\Product::class)`)
- Filter bar:
  - Search input (name/SKU) — `?search=`
  - Category dropdown — `?category_id=`
  - Active filter — `?active=1|0`
  - Submit + Clear links
- Table columns: SKU | Name | Category | Regular Price | Status | Actions
  - Regular Price: formatted currency; show "SALE" badge if `sale_price` is set
  - Status: green badge (Active) / gray badge (Inactive)
  - Actions: View, Edit, Toggle Active, Delete
- Empty state when no products found
- Pagination links

---

## show.blade.php

**Purpose:** Product detail page.

**Sections:**
- Back link to index
- Header: product name + status badge
- Two-column layout:
  - Left:
    - SKU (read-only)
    - Category (link or "Uncategorised")
    - Regular Price
    - Sale Price (if set — shown as green badge "On Sale: $X.XX")
    - Purchase Price (internal — label "Purchase Price (internal, not shown to customers)")
    - Description
    - Notes (label "Internal Notes")
  - Right: Listings count card + "Manage Listings" link → `product-listings.index?product_id={id}`
- Timestamps (created/updated)
- Edit + Delete buttons (gated by policy)
- Listings table preview (first 5, with link to full list)

---

## create.blade.php / edit.blade.php

**Purpose:** Wrapper pages that include `_form.blade.php`.

`create.blade.php`:
```blade
@extends('layouts.admin')
@section('content')
    <x-page-header title="New Product" :back="route('products.index')" />
    @include('products._form', ['product' => null, 'categories' => $categories])
@endsection
```

`edit.blade.php`:
```blade
@extends('layouts.admin')
@section('content')
    <x-page-header title="Edit Product" :back="route('products.show', $product)" />
    @include('products._form', ['product' => $product, 'categories' => $categories])
@endsection
```

---

## _form.blade.php

**Purpose:** Shared create/edit form. Detects create vs edit by `isset($product)`.

**Fields:**

| Field | Type | Notes |
|-------|------|-------|
| SKU | text input | Show on create only (readonly text on edit — SKU is immutable) |
| Name | text input | required |
| Category | select | from `$categories` dropdown; null option "— Uncategorised —" |
| Regular Price | number input | step=0.01, min=0, required |
| Sale Price | number input | step=0.01, min=0, optional; label "Sale Price (leave blank if not on sale)" |
| Purchase Price | number input | step=0.01, min=0, optional; label "Purchase Price (internal — not shown to customers)" |
| Description | textarea | rows=4 |
| Notes | textarea | rows=2; label "Internal Notes (not shown to customers)" |
| Active | toggle/checkbox | default checked |

**Form action:**
- Create: `POST /admin/products`
- Edit: `PATCH /admin/products/{product}`

**Validation errors:** `@error` directives on each field.

**Save button:** "Create Product" / "Save Changes"

---

## Key UI Decisions

### SKU display on edit
Show SKU as read-only text (not an input) on the edit form with a note:
> "SKU cannot be changed after creation."

### Purchase price visibility
`purchase_price` visible to admin/staff in the admin panel only.
Never rendered in any customer-facing view (portal).

### Sale price display
When `sale_price` is set on the index or show page:
- Show `sale_price` as the prominent price in green
- Show `regular_price` struck-through next to it

### Category dropdown
Uses `$categories` (active only, ordered by name).
Includes a null/blank option for "Uncategorised".

## Checklist
- [ ] index: search + category + active filters
- [ ] index: table with SKU, name, category, regular_price (+sale badge), status, actions
- [ ] show: purchase_price labelled as internal only
- [ ] show: sale_price displayed as "On Sale" with strike-through of regular_price
- [ ] show: listings count + link to product-listings filtered by product
- [ ] _form: SKU input only on create; readonly display on edit
- [ ] _form: sale_price optional field with clear label
- [ ] All forms use `@csrf` + correct `@method` for PATCH
- [ ] Validation errors shown with `@error` on each field
