# Customer Module — Views

All views extend the app layout. Use Tailwind CSS v3 classes only.
No inline styles. No JavaScript frameworks — plain HTML + Tailwind.

---

## View Files
| File | Route |
|------|-------|
| `resources/views/customers/index.blade.php` | GET /customers |
| `resources/views/customers/show.blade.php` | GET /customers/{customer} |
| `resources/views/customers/create.blade.php` | GET /customers/create |
| `resources/views/customers/edit.blade.php` | GET /customers/{customer}/edit |

---

## 1. index.blade.php

**Purpose:** Paginated list of customers with search + status filter.

**Layout:** `<x-app-layout>`

**Sections:**
1. Page header — title "Customers" + "Add Customer" button (show only if `auth()->user()->can('customers.create')`)
2. Filter bar — search input + status dropdown + submit button
3. Customer table — columns: Name, Email, Phone, Company, Status, Created, Actions
4. Pagination links

**Table columns:**
| Column | Value |
|--------|-------|
| Name | `{{ $customer->name }}` |
| Email | `{{ $customer->email }}` |
| Phone | `{{ $customer->phone }}` |
| Company | `{{ $customer->company_name ?? '—' }}` |
| Status | Badge with color from `$customer->status->color()` and text from `$customer->status->label()` |
| Created | `{{ $customer->created_at->format('M d, Y') }}` |
| Actions | View link, Edit link (if can update), Delete button (if can delete) |

**Status badge colors (Tailwind):**
- green → `bg-green-100 text-green-800`
- yellow → `bg-yellow-100 text-yellow-800`
- red → `bg-red-100 text-red-800`

**Search/Filter form:**
```html
<form method="GET" action="{{ route('customers.index') }}">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, company..." />
    <select name="status">
        <option value="">All Statuses</option>
        @foreach($statuses as $status)
            <option value="{{ $status->value }}" {{ ($filters['status'] ?? '') === $status->value ? 'selected' : '' }}>
                {{ $status->label() }}
            </option>
        @endforeach
    </select>
    <button type="submit">Filter</button>
    <a href="{{ route('customers.index') }}">Clear</a>
</form>
```

**Delete button — must use a form with POST + method spoofing:**
```html
<form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

**Empty state:** If `$customers->isEmpty()`, show a centered message: "No customers found."

**Pagination:** `{{ $customers->links() }}`

---

## 2. show.blade.php

**Purpose:** Full customer profile. Show all fields + current status + change status form.

**Layout:** `<x-app-layout>`

**Sections:**
1. Page header — "Customer: {{ $customer->name }}" + Edit button (if can update) + Back to list link
2. Customer detail card — all fields in a definition list or grid
3. Status section — current status badge + change status form (if can changeStatus)

**Fields to display:**
| Label | Value |
|-------|-------|
| Name | `{{ $customer->name }}` |
| Email | `{{ $customer->email }}` |
| Phone | `{{ $customer->phone }}` |
| Company | `{{ $customer->company_name ?? '—' }}` |
| Address | `{{ $customer->address }}` |
| City | `{{ $customer->city }}` |
| State | `{{ $customer->state }}` |
| Postal Code | `{{ $customer->postal_code }}` |
| Country | `{{ $customer->country }}` |
| Status | Badge (same color logic as index) |
| Created | `{{ $customer->created_at->format('M d, Y') }}` |

**Change Status Form (show only if `auth()->user()->can('customers.changeStatus', $customer)`):**
```html
<form method="POST" action="{{ route('customers.changeStatus', $customer) }}">
    @csrf
    @method('PATCH')
    <select name="status">
        @foreach($statuses as $status)
            <option value="{{ $status->value }}" {{ $customer->status === $status ? 'selected' : '' }}>
                {{ $status->label() }}
            </option>
        @endforeach
    </select>
    <button type="submit">Update Status</button>
</form>
```

---

## 3. create.blade.php

**Purpose:** Form to create a new customer.

**Layout:** `<x-app-layout>`

**Sections:**
1. Page header — "Add Customer" + Back to list link
2. Form — all fields
3. Submit + Cancel buttons

**Form:**
```html
<form method="POST" action="{{ route('customers.store') }}">
    @csrf
    <!-- Fields: name, email, phone, company_name, address, city, state, postal_code, country, status -->
    <!-- All required except company_name -->
    <!-- Show @error('field') validation messages below each input -->
</form>
```

**Field inputs:**
| Field | Input Type | Required | Default |
|-------|-----------|----------|---------|
| name | text | Yes | old('name') |
| email | email | Yes | old('email') |
| phone | text | Yes | old('phone') |
| company_name | text | No | old('company_name') |
| address | text | Yes | old('address') |
| city | text | Yes | old('city') |
| state | text | Yes | old('state') |
| postal_code | text | Yes | old('postal_code') |
| country | text | Yes | old('country') |
| status | select | Yes | old('status', 'active') |

**Status select options:**
```html
<select name="status">
    @foreach($statuses as $status)
        <option value="{{ $status->value }}" {{ old('status', 'active') === $status->value ? 'selected' : '' }}>
            {{ $status->label() }}
        </option>
    @endforeach
</select>
```

**Validation error display (repeat for each field):**
```html
@error('name')
    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror
```

---

## 4. edit.blade.php

**Purpose:** Form to edit an existing customer. Pre-filled with current values.

**Layout:** `<x-app-layout>`

**Sections:**
1. Page header — "Edit Customer: {{ $customer->name }}" + Back to show link
2. Form — all fields pre-filled
3. Submit + Cancel buttons

**Form:**
```html
<form method="POST" action="{{ route('customers.update', $customer) }}">
    @csrf
    @method('PUT')
    <!-- Same fields as create, but values use old('field', $customer->field) -->
</form>
```

**Pre-fill pattern:**
```html
value="{{ old('name', $customer->name) }}"
```

**Status select pre-fill:**
```html
<select name="status">
    @foreach($statuses as $status)
        <option value="{{ $status->value }}" {{ old('status', $customer->status->value) === $status->value ? 'selected' : '' }}>
            {{ $status->label() }}
        </option>
    @endforeach
</select>
```

---

## Flash Message Display

Add to the app layout (or top of each view if layout doesn't include it):
```html
@if(session('success'))
    <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-4">
        {{ session('success') }}
    </div>
@endif
```

---

## General Notes
- All forms include `@csrf`
- PUT/DELETE forms include `@method('PUT')` or `@method('DELETE')`
- All inputs use `old()` to repopulate after validation errors
- Authorization checks in views use `@can` or `auth()->user()->can()` to show/hide buttons
- Never show Edit/Delete buttons to users who don't have the permission
