# Customer Module — Views

All views live in `resources/views/customers/`.
**All views must be fully responsive** (mobile + desktop) using Tailwind CSS v3.
Use the same patterns as `users/` and `departments/` views.

> Import/Export views are excluded from this module. No `import.blade.php` needed.

---

## Index: `customers/index.blade.php`

### Desktop layout
- Page header: "Customers" h1 + "New Customer" button (`@can('create', Customer::class)`)
- Filter bar: search input, status dropdown, source dropdown, assigned-to dropdown
  (only shown to admin/manager), department dropdown (admin only), "Filter" button,
  conditional "Clear" link (only when any filter is active)
- Table with columns: Name (link to show), Company, Status badge, Source, Assigned To,
  Actions (View / Edit — Edit gated by `@can('update', $customer)`)
- Empty state: icon + "No customers found."
- Pagination links

### Mobile layout (responsive)
- Same filter bar collapses to vertical stack on small screens
- Table becomes card-per-row layout (same pattern as users/index)
- Filter and New Customer buttons stack vertically on mobile

### Status badge colors
| Status    | bg / text classes                  |
|-----------|------------------------------------|
| lead      | `bg-blue-100 text-blue-800`        |
| prospect  | `bg-yellow-100 text-yellow-800`    |
| active    | `bg-green-100 text-green-800`      |
| churned   | `bg-gray-100 text-gray-700`        |

---

## Create: `customers/create.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Create Customer</x-slot>

    @include('customers._form', ['customer' => null])
</x-app-layout>
```

---

## Edit: `customers/edit.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Edit Customer</x-slot>

    @include('customers._form', ['customer' => $customer])
</x-app-layout>
```

---

## Form Partial: `customers/_form.blade.php`

**Two-column grid** (`sm:grid-cols-1 md:grid-cols-2`) inside a white rounded card.

| Field          | Input type        | Notes                                        |
|----------------|-------------------|----------------------------------------------|
| First Name     | text              | required; col-span-1                         |
| Last Name      | text              | required; col-span-1                         |
| Email          | email             | nullable; col-span-1                         |
| Phone          | text              | nullable; col-span-1                         |
| Company Name   | text              | nullable; col-span-2 (full width)            |
| Job Title      | text              | nullable; col-span-1                         |
| Status         | select            | CustomerStatus cases; default Lead           |
| Source         | select            | CustomerSource cases; default Other          |
| Assigned To    | select            | sales users only; admin/manager can see      |
| Department     | select            | all departments; nullable                    |
| Address Line 1 | text              | nullable; col-span-2                         |
| Address Line 2 | text              | nullable; col-span-2                         |
| City           | text              | col-span-1                                   |
| State          | text              | col-span-1                                   |
| Postcode       | text              | col-span-1                                   |
| Country        | text              | col-span-1; default Australia                |
| Notes          | textarea (rows=4) | nullable; col-span-2 (full width)            |

**Buttons row** (below grid):
- Primary: "Create Customer" (new) / "Save Changes" (edit)
- Secondary: "Cancel" → `route('customers.index')`

On mobile: all fields stack single-column; buttons stack vertically.

---

## Show: `customers/show.blade.php`

**Desktop**: Two-column `lg:grid-cols-3` layout.
**Mobile**: Single column, cards stacked vertically.

### Left column (1/3) — Identity card
```
Full name (large, bold)
Company name · Job title (subtitle, gray)
─────────────────────────
Status badge (color from CustomerStatus::color())
Source badge (gray)
─────────────────────────
📧 email (mailto link, or "—")
📞 phone (tel link, or "—")
─────────────────────────
Assigned To: [link to users.show] or "Unassigned"
Department:  [link to departments.show] or "—"
```

### Right column (2/3) — Detail cards

**Card: Address**
- Show all address fields, or "No address on file." if all null.
- Full-width on mobile.

**Card: Notes**
- Formatted paragraph, or "No notes." if empty.

**Card: Assign & Status** (admin + manager only — `@can('assign', $customer)`)
- Inline form → `customers.assign` (POST): select sales rep + "Assign" button
- Inline form → `customers.change-status` (POST): select status + "Update" button
- These two forms are displayed side-by-side on desktop, stacked on mobile.

**Card: Audit**
- Created by / Created at
- Last updated by / Last updated at

### Action buttons (top-right on desktop, below header on mobile)
- "Edit" → `customers.edit` (gated by `@can('update', $customer)`)
- "Delete" → DELETE form (gated by `@can('delete', $customer)`) with JS confirm
- "Restore" → POST form (gated by `@can('restore', $customer)`) — only shown when soft-deleted

---

## Flash Messages

All views use `@include('partials.flash')` at the top of the page body (already in app layout).

---

## Responsive Guidelines

- All buttons: full-width on mobile (`w-full sm:w-auto`)
- Tables: horizontal scroll on mobile (`overflow-x-auto`)
- Cards: full-width on mobile, grid on `lg:`
- Form fields: full-width always
- Navigation link: added to both desktop nav AND responsive (mobile) hamburger menu
