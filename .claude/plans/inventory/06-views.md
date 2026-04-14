# Inventory Module — Views

## View Files

```
resources/views/inventory/
├── index.blade.php                  — stock overview dashboard
├── show-by-sku.blade.php            — locations holding a SKU + count at each
└── show-by-sku-at-location.blade.php — serial numbers for one SKU at one location
```

All views use `<x-app-layout>` and Tailwind CSS v3.

---

## index.blade.php

**Purpose:** Stock overview dashboard — one row per product showing total `in_stock` serial count.
Each row links to `route('inventory.by-sku', $product)`.

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Stock Overview
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            @if ($stockOverview->isEmpty())
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-sm text-gray-500">No stock on hand. No serials with status <em>in_stock</em> found.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty On Hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($stockOverview as $productId => $serials)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-700">
                                        {{ $serials->first()->product->sku }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $serials->first()->product->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                        {{ $serials->count() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('inventory.by-sku', $serials->first()->product) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
```

> **Note:** The controller passes `$stockOverview` (keyed by product_id). Each value is a Collection
> of `InventorySerial` models with `product` eager-loaded.

---

## show-by-sku.blade.php

**Purpose:** For one product, show a table of locations with serial count at each.
Each location row links to `route('inventory.by-sku-at-location', [$product, $location])`.

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('inventory.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">← Stock Overview</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Stock by SKU: <span class="font-mono">{{ $product->sku }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Product summary card --}}
            <div class="mb-6 rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-600">
                    <span class="font-medium text-gray-800">Product:</span> {{ $product->name }}
                    &nbsp;|&nbsp;
                    <span class="font-medium text-gray-800">Total On Hand:</span>
                    <span class="font-semibold text-indigo-700">
                        {{ $stockByLocation->flatten()->count() }}
                    </span> units
                </p>
            </div>

            @if ($stockByLocation->isEmpty())
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-sm text-gray-500">
                        No in_stock serials found for this product at any location.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Units Here</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($stockByLocation as $locationId => $serials)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-700">
                                        {{ $serials->first()->location->code }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $serials->first()->location->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                        {{ $serials->count() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('inventory.by-sku-at-location', [$product, $serials->first()->location]) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            View Serials
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
```

> **Note:** `$stockByLocation` is keyed by `inventory_location_id`. Each value is a Collection
> of `InventorySerial` models with `location` eager-loaded.

---

## show-by-sku-at-location.blade.php

**Purpose:** Table of serial numbers for one SKU at one specific location.
Each row links to the inventory-serial detail page.

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('inventory.by-sku', $product) }}"
               class="text-sm text-gray-500 hover:text-gray-700">← {{ $product->sku }}</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Serials: <span class="font-mono">{{ $product->sku }}</span>
                at <span class="font-mono">{{ $location->code }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Summary card --}}
            <div class="mb-6 rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-600">
                    <span class="font-medium text-gray-800">Product:</span> {{ $product->name }}
                    &nbsp;|&nbsp;
                    <span class="font-medium text-gray-800">Location:</span> {{ $location->name }}
                    &nbsp;|&nbsp;
                    <span class="font-medium text-gray-800">Units:</span>
                    <span class="font-semibold text-indigo-700">{{ $serials->count() }}</span>
                </p>
            </div>

            @if ($serials->isEmpty())
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-sm text-gray-500">
                        No in_stock serials for this SKU at this location.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received At</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($serials as $serial)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-700">
                                        {{ $serial->serial_number }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ $serial->received_at->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('inventory-serials.show', $serial) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
```

> **Note:** `$serials` is a flat `Collection<int, InventorySerial>` ordered by `serial_number`,
> with both `product` and `location` eager-loaded (though the view only needs `serial_number`
> and `received_at` from the serial itself).

---

## Notes

- All three views use `<x-app-layout>` matching the existing admin layout convention.
- `@include('partials.flash')` is included on the dashboard (index) for session flash messages.
- The `font-mono` class is applied to SKU, location code, and serial number columns for readability.
- The drill-down flow is strictly: index → show-by-sku → show-by-sku-at-location. There is no "browse by location" starting point.
- **Cross-module dependency:** `show-by-sku-at-location.blade.php` links to `route('inventory-serials.show', $serial)` which is registered by the inventory-serial module. The inventory-serial module must be built and migrated before this view can be used in production. In feature tests for the inventory module, stub or seed at least one `InventorySerial` so the route resolves.
