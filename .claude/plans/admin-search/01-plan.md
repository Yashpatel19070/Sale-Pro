# Admin Searchable Selects — Tom Select

> Cross-cutting UI feature. Applies to any admin form with long dropdown lists.
> Depends on: Alpine.js (already installed), Tom Select (npm install needed).

---

## Why Tom Select

| | Tom Select | Select2 |
|---|---|---|
| jQuery dep | ✗ None | ✓ Required |
| ES module | ✓ | ✗ |
| Vite/Alpine | ✓ native | ✗ shim needed |
| AJAX | ✓ built-in | ✓ |
| Size | ~18 KB gz | ~28 KB gz |
| Status | Active | Maintenance only |

---

## Phase 1 — Alpine directive + preloaded JSON (order create form)

No AJAX endpoints needed. Suitable when list < ~500 items.

### 1.1 Install

```bash
npm install tom-select
```

### 1.2 `resources/js/app.js`

```js
import Alpine from 'alpinejs';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.min.css';

window.Alpine = Alpine;
window.TomSelect = TomSelect;

/**
 * x-ts directive — wrap any <select> with Tom Select.
 * Usage: <select x-ts> or <select x-ts="{ placeholder: 'Search...' }">
 *        <select x-ts="listingTsConfig(line)">   ← method returning config object
 *
 * el._ts = ts stored so @change handlers can access option data if needed.
 */
Alpine.directive('ts', (el, { expression }, { evaluate, cleanup }) => {
    const overrides = expression ? evaluate(expression) : {};
    const ts = new TomSelect(el, {
        allowEmptyOption: true,
        ...overrides,
    });
    el._ts = ts;
    cleanup(() => { delete el._ts; ts.destroy(); });
});

Alpine.start();
```

### 1.3 `vite.config.js` — no change needed (Vite handles CSS import).

### 1.4 `tailwind.config.js` — add Tom Select CSS to content scan

```js
content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './node_modules/tom-select/dist/css/tom-select.default.min.css',
],
```

---

## Usage patterns

### Customer select (preloaded, custom render)

```blade
{{-- In controller: $customers = Customer::active()->get(['id','name','email']) --}}
<script>window.__customers = @json($customers);</script>

<select name="customer_id" x-ts="{
    valueField: 'id',
    labelField: 'label',
    searchField: ['name', 'email'],
    options: window.__customers.map(c => ({
        ...c,
        label: c.name + ' — ' + c.email,
    })),
    render: {
        option: (d, e) =>
            `<div class='flex flex-col'>
                <span class='font-medium'>${e(d.name)}</span>
                <span class='text-xs text-gray-400'>${e(d.email)}</span>
             </div>`,
        item: (d, e) => `<span>${e(d.name)}</span>`,
    },
}">
    <option value=''>Select customer...</option>
</select>
```

### Product / SKU select (preloaded)

```blade
{{-- In controller: $products = Product::active()->get(['id','sku','name','regular_price','sale_price']) --}}
<script>window.__products = @json($products);</script>

<select name="product_id" x-ts="{
    valueField: 'id',
    labelField: 'label',
    searchField: ['sku', 'name'],
    options: window.__products.map(p => ({
        ...p,
        label: p.sku + ' — ' + p.name,
    })),
    render: {
        option: (d, e) =>
            `<div class='flex items-center gap-2'>
                <span class='font-mono text-xs text-gray-400 w-20 shrink-0'>${e(d.sku)}</span>
                <span>${e(d.name)}</span>
             </div>`,
        item: (d, e) => `<span class='font-mono text-xs mr-1 text-gray-500'>${e(d.sku)}</span> ${e(d.name)}`,
    },
}">
    <option value=''>Search product or SKU...</option>
</select>
```

### Inventory location select (simple preloaded)

```blade
<select name="location_id" x-ts="{ placeholder: 'Select location...' }">
    <option value=''>Select location...</option>
    @foreach($locations as $loc)
        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
    @endforeach
</select>
```

### Serial number select (preloaded, filtered by product + location)

When used inside an Alpine.js form with cascading state, pass filtered options reactively:

```blade
<select x-model="line.serial_id"
        :disabled="!line.location_id"
        x-ts="{
            valueField: 'id',
            labelField: 'serial_number',
            searchField: ['serial_number'],
            options: availableSerials(line),
        }">
    <option value=''>Search serial #...</option>
</select>
```

> Note: Tom Select does not react to Alpine state changes automatically.
> When `availableSerials(line)` changes (location changes), call `ts.clearOptions()` then `ts.addOptions(newList)`.
> Simpler alternative for small lists: use plain Alpine `<select>` (no x-ts) for cascaded serials — Tom Select only on the first two levels.

---

## Phase 2 — AJAX search endpoints (for large datasets, > 500 rows)

### Endpoints (implemented)

| Route | Controller | Permission | Returns |
|---|---|---|---|
| `GET /admin/product-listings/search?q=` | `ProductListingController::search` | `viewAny ProductListing` | `[{id,title,sku,product_id,price,label}]` × 30 |
| `GET /admin/inventory-locations/search?product_id=` | `InventoryLocationController::search` | `viewAny InventoryLocation` | `[{id,name}]` — only locations with in-stock serials for that product |
| `GET /admin/inventory-serials/search?product_id=&location_id=` | `InventorySerialController::search` | `viewAny InventorySerial` | `[{id,serial_number}]` — in-stock serials at that location |
| `GET /admin/customers/search?q=` | not yet — customers still preloaded | — | — |
| `GET /admin/products/search?q=` | not yet | — | — |

Routes added in `routes/web.php` (static route before wildcard in each prefix group):
```php
Route::get('product-listings/search', [ProductListingController::class, 'search'])->name('product-listings.search');
// inside inventory-locations prefix group:
Route::get('/search', [InventoryLocationController::class, 'search'])->name('search');
// inside inventory-serials prefix group:
Route::get('/search', [InventorySerialController::class, 'search'])->name('search');
```

### AJAX cascading pattern — `listingTsConfig(line)` (order create form)

Defined as a method in `x-data`, called as `x-ts="listingTsConfig(line)"`. Alpine evaluates this in x-for scope so `line` is the reactive proxy captured in the closure.

```js
listingTsConfig(line) {
    return {
        valueField: 'value',
        labelField: 'label',
        searchField: ['label'],
        load(query, callback) {
            if (query.length < 2) return callback();
            fetch('/admin/product-listings/search?q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => callback(data.map(l => ({
                    value: String(l.id),
                    label: l.label,          // "SKU-001 — Widget Pro"
                    product_id: l.product_id,
                    sku: l.sku,
                    price: l.price,
                }))))
                .catch(() => callback());
        },
        shouldLoad(q) { return q.length >= 2; },
        onItemAdd(value) {
            // 'this' = Tom Select instance; this.options[value] is always populated
            const opt = this.options[value];
            if (!opt) return;
            line.product_id = opt.product_id;
            line.sku        = opt.sku || '';
            line.unit_price = parseFloat(opt.price || 0);
            line.location_id = null; line.serial_id = null;
            line.availableLocations = []; line.availableSerials = [];
            fetch('/admin/inventory-locations/search?product_id=' + opt.product_id)
                .then(r => r.json())
                .then(locs => { line.availableLocations = locs; })
                .catch(() => {});
        },
        onItemRemove() {
            line.product_id = null; line.sku = ''; line.unit_price = 0;
            line.location_id = null; line.serial_id = null;
            line.availableLocations = []; line.availableSerials = [];
        },
    };
},
```

> **Why `onItemAdd` not `@change`:** Tom Select fires `change` on the hidden underlying `<select>`, but reading `event.target._ts.options[value]` from an Alpine `@change` handler is unreliable (event propagation, timing). `onItemAdd(value)` fires inside Tom Select with `this` = TS instance — `this.options[value]` is always present and correct.

Location → serial cascade handled by plain Alpine `@change` on the location `<select>`:
```js
async onLocationChange(line) {
    line.serial_id = null; line.availableSerials = [];
    if (line.product_id && line.location_id) {
        const res = await fetch(
            '/admin/inventory-serials/search?product_id=' + line.product_id +
            '&location_id=' + line.location_id
        );
        line.availableSerials = await res.json();
    }
},
```

---

## CSS / theming

Tom Select default CSS is included via the JS import. To align with Tailwind form styles, override in `resources/css/app.css`:

```css
.ts-wrapper.form-control,
.ts-wrapper.form-select {
    padding: 0;
}

.ts-control {
    @apply block w-full rounded-md border-gray-300 text-sm shadow-sm;
    @apply focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500;
}

.ts-dropdown {
    @apply text-sm rounded-md shadow-lg border border-gray-200;
}

.ts-dropdown .option.active {
    @apply bg-indigo-50 text-indigo-700;
}

.ts-dropdown .option:hover {
    @apply bg-gray-50;
}
```

---

## Implementation order

| Step | What | Status |
|---|---|---|
| 1 | `npm install tom-select` + update `app.js` with directive | ✅ done |
| 2 | Add CSS override block to `app.css` | ✅ done |
| 3 | Apply `x-ts` to order create: customer select | ✅ done |
| 4 | AJAX endpoints: product-listings/search, inventory-locations/search, inventory-serials/search | ✅ done |
| 5 | Order create: listing select → Tom Select AJAX load(); locations/serials → async fetch cascade | ✅ done |
| 6 | Apply `x-ts` to purchase order create: supplier select | not started |
| 7 | Apply `x-ts` to all other existing admin create/edit forms | not started |

---

## Implemented: order create form

See `order/03-controllers.md` → create view spec.

| Select | Approach | Status |
|---|---|---|
| Customer `#customer_id` | `x-ts` plain (DOM options preloaded) | ✅ done |
| Listing per row | `x-ts="listingTsConfig(line)"` — AJAX `load()` + `onItemAdd` cascade | ✅ done |
| Location per row | plain Alpine `<select>` with `x-for="loc in line.availableLocations"` | ✅ done |
| Serial per row | plain Alpine `<select>` with `x-for="ser in line.availableSerials"` | ✅ done |

Location and serial stay as plain Alpine selects — their options are already small (filtered by product+location) and arrive via the cascade fetch, so Tom Select adds no value there.
