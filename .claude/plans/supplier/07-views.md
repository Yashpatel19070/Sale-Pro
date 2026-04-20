# Supplier Module — Views

Four Blade views. All extend `x-layouts.app`. Flash messages from layout.
Use `{{ }}` always — never `{!! !!}`. Use `@can` to hide unauthorized actions.

---

## 1. index.blade.php

**File:** `resources/views/suppliers/index.blade.php`

### What it shows
- Page title: "Suppliers"
- Button: "Add Supplier" (hidden if `@cannot('create', App\Models\Supplier::class)`)
- Search form: text input for search, select for status filter, submit button, clear link
- Table columns: Name, Contact, Email, Phone, Payment Terms, Status (badge), Actions
- Status badge: green pill for Active, yellow pill for Inactive (use `$supplier->status->color()` and `$supplier->status->label()`)
- Actions per row: View, Edit (hidden if `@cannot('update', $supplier)`), Delete form (hidden if `@cannot('delete', $supplier)`)
- Delete: POST form with `@method('DELETE')`, `onclick="return confirm('...')"` on button
- Pagination: `{{ $suppliers->links() }}`
- Empty state: "No suppliers found." when table is empty

### Flash messages
```blade
@if (session('success'))
    <div class="...">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="...">{{ session('error') }}</div>
@endif
```

### Filter form structure
```blade
<form method="GET" action="{{ route('suppliers.index') }}">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search...">
    <select name="status">
        <option value="">All Statuses</option>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                {{ $status->label() }}
            </option>
        @endforeach
    </select>
    <button type="submit">Search</button>
    <a href="{{ route('suppliers.index') }}">Clear</a>
</form>
```

---

## 2. show.blade.php

**File:** `resources/views/suppliers/show.blade.php`

### What it shows
- Page title: supplier name
- All fields in a detail card: Name, Contact Name, Email, Phone, Address (full), Payment Terms, Notes, Status (badge), Created At
- Change status form: select with `SupplierStatus` options, submit button (hidden if `@cannot('changeStatus', $supplier)`)
- Buttons: "Edit" (link to `suppliers.edit`, hidden if `@cannot('update', $supplier)`), "Back to Suppliers"
- Delete form at bottom (hidden if `@cannot('delete', $supplier)`)

### Change status form
```blade
@can('changeStatus', $supplier)
<form method="POST" action="{{ route('suppliers.changeStatus', $supplier) }}">
    @csrf
    @method('PATCH')
    <select name="status">
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected($supplier->status === $status)>
                {{ $status->label() }}
            </option>
        @endforeach
    </select>
    <button type="submit">Update Status</button>
</form>
@endcan
```

---

## 3. create.blade.php

**File:** `resources/views/suppliers/create.blade.php`

### What it shows
- Page title: "Add Supplier"
- Form: POST to `suppliers.store` with `@csrf`
- All 11 fields with labels, inputs, `old()` values, `@error` blocks
- Required fields: Name, Email, Phone, Status
- Nullable fields: Contact Name, Address, City, State, Postal Code, Country, Payment Terms, Notes
- Status select: required, default to `active`
- Buttons: "Save Supplier", "Cancel" (link back to index)

### Field layout pattern
```blade
<div>
    <label for="name">Name <span>*</span></label>
    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
    @error('name') <p>{{ $message }}</p> @enderror
</div>
```

### Notes textarea
```blade
<div>
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
    @error('notes') <p>{{ $message }}</p> @enderror
</div>
```

---

## 4. edit.blade.php

**File:** `resources/views/suppliers/edit.blade.php`

### What it shows
- Page title: "Edit Supplier"
- Form: PUT to `suppliers.update` with `@csrf` + `@method('PUT')`
- Identical fields to create — pre-filled with `old('field', $supplier->field)`
- Status select pre-selects current status using `@selected($supplier->status->value === $status->value)`
- Buttons: "Update Supplier", "Cancel" (link to `suppliers.show`)

### Pre-fill pattern
```blade
<input type="text" name="name" value="{{ old('name', $supplier->name) }}">
```

### Status select pre-fill
```blade
<select name="status">
    @foreach ($statuses as $status)
        <option value="{{ $status->value }}"
            @selected(old('status', $supplier->status->value) === $status->value)>
            {{ $status->label() }}
        </option>
    @endforeach
</select>
```

---

## Common Rules for All Views
- `@csrf` on every form
- `{{ }}` everywhere — never `{!! !!}`
- `old('field')` on create, `old('field', $model->field)` on edit
- `@error('field') <p class="...">{{ $message }}</p> @enderror` on every input
- `@can` / `@cannot` gates wrap every action button/form
- Status badge: `<span class="bg-{{ $supplier->status->color() }}-100 text-{{ $supplier->status->color() }}-800">{{ $supplier->status->label() }}</span>`
- Confirm dialog on delete: `onclick="return confirm('Are you sure you want to delete this supplier?')"`
- Flash messages shown at top of content area on every view

---

## 5. Navigation Update

**File:** `resources/views/layouts/navigation.blade.php`

Add a **Procurement** dropdown after the Inventory dropdown and before the Admin dropdown.
This dropdown will grow to include Purchase Orders and Returns when those modules are built.

### Desktop nav (inside the `<div class="hidden sm:flex ...">` section)
```blade
{{-- Procurement dropdown --}}
<x-dropdown align="left" width="48">
    <x-slot name="trigger">
        <button class="inline-flex items-center px-1 pt-1 ...">
            {{ __('Procurement') }}
            <svg class="ml-1 h-4 w-4" ...></svg>
        </button>
    </x-slot>
    <x-slot name="content">
        <x-dropdown-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">
            {{ __('Suppliers') }}
        </x-dropdown-link>
    </x-slot>
</x-dropdown>
```

### Responsive nav (inside the `<div class="pt-2 pb-3 space-y-1">` section)
```blade
{{-- Procurement section --}}
<div class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">
    {{ __('Procurement') }}
</div>
<x-responsive-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">
    {{ __('Suppliers') }}
</x-responsive-nav-link>
```

### Note on existing dropdown component
Check how the Catalog and Inventory dropdowns are structured in `navigation.blade.php` and match the same `x-dropdown` component pattern exactly — the trigger button markup must be copied to stay consistent.
