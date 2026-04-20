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

Add a **Procurement** dropdown after the Inventory block and before the Admin block.
Uses the same inline Alpine.js pattern as Catalog and Inventory — no custom component needed.
This dropdown will grow to include Purchase Orders and Returns when those modules are built.

### Desktop nav — add after `@endcanany` closing the Inventory block (~line 79)
```blade
@can('suppliers.viewAny')
    <div class="relative flex items-stretch" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('suppliers.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out">
            {{ __('Procurement') }}
            <svg class="ms-1 fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             style="display:none"
             @click="open = false"
             class="absolute top-full start-0 z-50 mt-1 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 py-1">
            <x-dropdown-link :href="route('suppliers.index')">{{ __('Suppliers') }}</x-dropdown-link>
        </div>
    </div>
@endcan
```

### Responsive nav — add after `@endcanany` closing the Inventory block (~line 193)
```blade
@can('suppliers.viewAny')
    <div class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('Procurement') }}</div>
    <x-responsive-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">
        {{ __('Suppliers') }}
    </x-responsive-nav-link>
@endcan
```
