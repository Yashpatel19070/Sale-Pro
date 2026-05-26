# Order Module — AvaTax Integration (Phase 2)

Covers all changes needed to wire AvaTax tax calculation into the order create/edit form.
AvaTax logic lives in `AvaTaxService` — the controller and service only delegate.

---

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Controllers/OrderController.php` | Add `calculateTax()` action |
| `routes/web.php` | Add `POST /admin/orders/calculate-tax` route |
| `app/Http/Requests/Order/StoreOrderRequest.php` | Add `lines.*.tax_amount` rule |
| `app/Http/Requests/Order/UpdateOrderRequest.php` | Add `lines.*.tax_amount` rule |
| `app/Services/OrderService.php` | Use submitted `tax_amount` directly, stop re-computing |
| `resources/views/orders/create.blade.php` | `customersData` + Alpine tax state + `fetchAllLineTax()` + hidden inputs |
| `resources/views/orders/edit.blade.php` | Rewrite line items as `<table>` matching create form; add `onProductChange()` + `loadStock()`; editable `tax_amount` input; JS vars for tax_exempt + shipping snapshot |
| `tests/Unit/OrderServiceTest.php` | 2 new tests verifying submitted tax_amount passthrough |
| `tests/Feature/OrderControllerTest.php` | 10 new tests for calculateTax endpoint + form rendering |

---

## RED — Write tests first

### `tests/Unit/OrderServiceTest.php` — add these tests

```php
it('store_uses_submitted_tax_amount_not_recalculated', function () {
    $customer = Customer::factory()->create();
    $listing  = ProductListing::factory()->active()->create();
    $serial   = InventorySerial::factory()->inStock()->create(['product_id' => $listing->product_id]);
    $location = InventoryLocation::factory()->create();
    $serial->update(['inventory_location_id' => $location->id]);
    $user     = User::factory()->create();

    // AvaTax returns 2.73 — submit that exactly
    $data = [
        'customer_id'     => $customer->id,
        'source'          => 'walk_in',
        'payment_method'  => 'cash',
        'shipping'        => 0,
        'lines'           => [[
            'product_listing_id' => $listing->id,
            'unit_price'         => 100.00,
            'tax_rate'           => 2.73,
            'tax_amount'         => 2.73,
        ]],
        'fees'            => [],
    ];

    $order = app(OrderService::class)->store($data, $user);

    // tax_amount must be EXACTLY the submitted value, not re-derived from tax_rate
    $this->assertDatabaseHas('order_lines', [
        'order_id'   => $order->id,
        'tax_amount' => '2.73',
    ]);
});

it('update_uses_submitted_tax_amount_not_recalculated', function () {
    $customer = Customer::factory()->create();
    $listing  = ProductListing::factory()->active()->create();
    $user     = User::factory()->create();
    $order    = Order::factory()->pending()->for($customer)->createdBy($user)->create();
    $line     = $order->lines()->create([
        'product_listing_id' => $listing->id,
        'product_name'       => 'Test',
        'sku'                => 'SKU-1',
        'unit_price'         => 100.00,
        'tax_rate'           => 0,
        'tax_amount'         => 0,
        'line_total'         => 100.00,
    ]);

    $data = [
        'shipping' => 0,
        'lines'    => [[
            'product_listing_id' => $listing->id,
            'unit_price'         => 100.00,
            'tax_rate'           => 8.2500,
            'tax_amount'         => 8.25,
        ]],
        'fees'     => [],
    ];

    app(OrderService::class)->update($order, $data);

    $this->assertDatabaseHas('order_lines', [
        'order_id'   => $order->id,
        'tax_amount' => '8.25',
    ]);
});
```

---

### `tests/Feature/OrderControllerTest.php` — add these tests

```php
// --- calculateTax endpoint ---

it('calculate_tax_returns_zeros_when_no_address', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => false]);

    $this->actingAs($admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id'     => $customer->id,
            'shipping_address' => null,
            'lines'            => [
                ['unit_price' => 100.00, 'sku' => 'PROD-A'],
            ],
        ])
        ->assertOk()
        ->assertJson([
            ['tax_rate' => 0, 'tax_amount' => 0],
        ]);
});

it('calculate_tax_returns_zeros_when_customer_is_tax_exempt', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => true]);
    $address  = CustomerAddress::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id'      => $customer->id,
            'shipping_address' => [
                'address_line1' => $address->address_line1,
                'city'          => $address->city,
                'state'         => $address->state,
                'postal_code'   => $address->postal_code,
                'country'       => $address->country,
            ],
            'lines' => [
                ['unit_price' => 100.00, 'sku' => 'PROD-A'],
            ],
        ])
        ->assertOk()
        ->assertJson([
            ['tax_rate' => 0, 'tax_amount' => 0],
        ]);
});

it('calculate_tax_returns_zeros_when_unit_price_is_zero', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => false]);
    $address  = CustomerAddress::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id'      => $customer->id,
            'shipping_address' => [
                'address_line1' => $address->address_line1,
                'city'          => $address->city,
                'state'         => $address->state,
                'postal_code'   => $address->postal_code,
                'country'       => $address->country,
            ],
            'lines' => [
                ['unit_price' => 0, 'sku' => 'FREE'],
            ],
        ])
        ->assertOk()
        ->assertJson([
            ['tax_rate' => 0, 'tax_amount' => 0],
        ]);
});

it('calculate_tax_delegates_to_avatax_service', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => false]);
    $address  = CustomerAddress::factory()->for($customer)->create();

    $stub = Mockery::mock(AvaTaxService::class);
    $stub->shouldReceive('isEnabled')->andReturn(true);
    $stub->shouldReceive('calculateTax')
        ->once()
        ->withArgs(fn($lines, $shipTo, $code) =>
            count($lines) === 1 &&
            $lines[0]['unit_price'] === 100.0 &&
            $shipTo['address_line1'] === $address->address_line1
        )
        ->andReturn([['tax_rate' => 8.25, 'tax_amount' => 8.25]]);
    $this->app->instance(AvaTaxService::class, $stub);

    $this->actingAs($admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id'      => $customer->id,
            'shipping_address' => [
                'address_line1' => $address->address_line1,
                'city'          => $address->city,
                'state'         => $address->state,
                'postal_code'   => $address->postal_code,
                'country'       => $address->country,
            ],
            'lines' => [
                ['unit_price' => 100.00, 'sku' => 'PROD-A'],
            ],
        ])
        ->assertOk()
        ->assertJson([
            ['tax_rate' => 8.25, 'tax_amount' => 8.25],
        ]);
});

it('calculate_tax_requires_auth', function () {
    $this->postJson(route('orders.calculate-tax'), [])
        ->assertUnauthorized();
});

// --- create form rendering ---

it('create_form_includes_tax_exempt_in_customers_json', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => true]);

    $html = $this->actingAs($admin)
        ->get(route('orders.create'))
        ->assertOk()
        ->content();

    // __orderCustomers JSON must include tax_exempt flag
    $this->assertStringContainsString('"tax_exempt":true', $html);
});

it('create_form_includes_address_fields_in_customers_json', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->for($customer)->create([
        'address_line1' => '100 Main St',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77001',
        'country'       => 'US',
    ]);

    $html = $this->actingAs($admin)
        ->get(route('orders.create'))
        ->assertOk()
        ->content();

    // address_line1 must appear inside __orderCustomers so Alpine can pass it to calculateTax
    $this->assertStringContainsString('"address_line1":"100 Main St"', $html);
    $this->assertStringContainsString('"postal_code":"77001"', $html);
});

// --- edit form rendering ---

it('edit_form_passes_tax_exempt_js_var', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['tax_exempt' => true]);
    $order    = Order::factory()->pending()->for($customer)->createdBy($admin)->create();

    $html = $this->actingAs($admin)
        ->get(route('orders.edit', $order))
        ->assertOk()
        ->content();

    $this->assertStringContainsString('window.__editTaxExempt = true', $html);
});

it('edit_form_passes_shipping_snapshot_js_var', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $order    = Order::factory()->pending()->for($customer)->createdBy($admin)->create([
        'shipping_address_line1' => '5426 N Shepherd Dr',
        'shipping_city'          => 'Houston',
        'shipping_state'         => 'TX',
        'shipping_postal_code'   => '77091',
        'shipping_country'       => 'US',
    ]);

    $html = $this->actingAs($admin)
        ->get(route('orders.edit', $order))
        ->assertOk()
        ->content();

    $this->assertStringContainsString('window.__editShipSnapshot', $html);
    $this->assertStringContainsString('5426 N Shepherd Dr', $html);
});

// --- editable tax_amount input ---

it('create_form_has_editable_tax_amount_input_not_hidden', function () {
    $admin = adminUser();

    $html = $this->actingAs($admin)
        ->get(route('orders.create'))
        ->assertOk()
        ->content();

    // Editable input must be present; a bare hidden input for tax_amount must NOT be the only one
    $this->assertStringContainsString('[tax_amount]', $html);
    // No standalone <input type="hidden" ... tax_amount ...> — it must be type="number"
    $this->assertStringNotContainsString('type="hidden" :name="\'lines[\' + i + \'][tax_amount]\'"', $html);
});

it('edit_form_has_editable_tax_amount_input_not_hidden', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $order    = Order::factory()->pending()->for($customer)->createdBy($admin)->create();

    $html = $this->actingAs($admin)
        ->get(route('orders.edit', $order))
        ->assertOk()
        ->content();

    $this->assertStringContainsString('[tax_amount]', $html);
    $this->assertStringNotContainsString('type="hidden" :name="\'lines[\' + i + \'][tax_amount]\'"', $html);
});

it('store_accepts_manually_overridden_tax_amount', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $listing  = ProductListing::factory()->active()->create();

    $this->actingAs($admin)
        ->post(route('orders.store'), [
            'customer_id'    => $customer->id,
            'source'         => 'walk_in',
            'payment_method' => 'cash',
            'shipping'       => 0,
            'lines'          => [[
                'product_listing_id' => $listing->id,
                'unit_price'         => 100.00,
                'tax_rate'           => 8.25,
                'tax_amount'         => 9.99,  // admin-overridden value
            ]],
            'fees' => [],
        ])
        ->assertRedirect();

    // The overridden tax_amount (9.99) must be persisted, not recalculated from tax_rate
    $this->assertDatabaseHas('order_lines', ['tax_amount' => '9.99']);
});
```

---

## GREEN — Implement

### Route (`routes/web.php`)

Add inside the `orders` resource group (before `Route::resource`):

```php
Route::post('orders/calculate-tax', [OrderController::class, 'calculateTax'])
    ->name('orders.calculate-tax');
```

> Must be declared **before** `Route::resource('orders', ...)` so it is not swallowed by `{order}` segment.

---

### `OrderController::calculateTax()`

```php
use App\Services\AvaTaxService;

public function calculateTax(Request $request, AvaTaxService $avatax): JsonResponse
{
    $this->authorize('viewAny', Order::class);

    $customerId      = $request->input('customer_id');
    $shippingAddress = $request->input('shipping_address');   // array|null
    $lines           = $request->input('lines', []);          // [{unit_price, sku}]

    $customer   = Customer::find($customerId);
    $zeros      = array_map(fn () => ['tax_rate' => 0, 'tax_amount' => 0], $lines);

    // No address → zeros
    if (empty($shippingAddress)) {
        return response()->json($zeros);
    }

    // Tax exempt → zeros
    if ($customer?->tax_exempt) {
        return response()->json($zeros);
    }

    return response()->json($avatax->calculateTax($lines, $shippingAddress, (string) $customerId));
}
```

---

### `StoreOrderRequest` — add to `rules()`

```php
'lines.*.tax_amount' => ['required', 'numeric', 'min:0'],
```

### `UpdateOrderRequest` — add to `rules()`

```php
'lines.*.tax_amount' => ['required', 'numeric', 'min:0'],
```

---

### `OrderService` — use submitted `tax_amount` directly

In both `store()` and `update()`, replace the computed `$taxAmount` line:

```php
// BEFORE (re-computes from rate — causes rounding drift):
$taxAmount = round((float) $line['unit_price'] * ((float) $line['tax_rate'] / 100), 2);

// AFTER (use what Alpine submitted — AvaTax already calculated it):
$taxAmount = round((float) ($line['tax_amount'] ?? 0), 2);
```

Line total unchanged: `round((float) $line['unit_price'] + $taxAmount, 2)`.

---

### `create.blade.php` — changes

#### 1. `$customersData` — add `tax_exempt` + address detail fields

```php
$customersData = $customers->map(fn($c) => [
    'id'         => $c->id,
    'name'       => $c->name,
    'tax_exempt' => $c->tax_exempt,
    'addresses'  => $c->addresses->map(fn($a) => [
        'id'            => $a->id,
        'label'         => $a->label,
        'summary'       => trim(implode(', ', array_filter([
            trim($a->first_name . ' ' . $a->last_name),
            $a->address_line1,
            $a->city,
        ]))),
        'is_default'    => $a->is_default,
        'address_line1' => $a->address_line1,
        'city'          => $a->city,
        'state'         => $a->state,
        'postal_code'   => $a->postal_code,
        'country'       => $a->country,
    ]),
]);
```

#### 2. Alpine `x-data` — add tax state per line and root state

```js
// In line initial state: add tax_amount field
lines: [{ product_listing_id: '', unit_price: '', tax_rate: 0, tax_amount: 0, sku: '', stock: '', stockLoading: false }],

// Root state additions:
taxExempt: false,
taxTimer:  null,
```

#### 3. `onCustomerChange()` — also set `taxExempt`

```js
onCustomerChange() {
    const c = this.customers.find(c => c.id == this.customerId);
    this.addresses        = c ? c.addresses : [];
    this.taxExempt        = c ? c.tax_exempt : false;
    this.billingAddressId  = '';
    this.shippingSelection = '';
    this.debounceTax();
},
```

#### 4. Add `debounceTax()` and `fetchAllLineTax()`

```js
debounceTax() {
    clearTimeout(this.taxTimer);
    this.taxTimer = setTimeout(() => this.fetchAllLineTax(), 400);
},

async fetchAllLineTax() {
    // Resolve the shipping address object from selected address ID
    const addr = this.shippingAddressId
        ? (this.addresses.find(a => a.id == this.shippingAddressId) || null)
        : null;

    const payload = {
        customer_id:      this.customerId,
        shipping_address: addr ? {
            address_line1: addr.address_line1,
            city:          addr.city,
            state:         addr.state,
            postal_code:   addr.postal_code,
            country:       addr.country,
        } : null,
        lines: this.lines.map(l => ({
            unit_price: parseFloat(l.unit_price) || 0,
            sku:        l.sku || '',
        })),
    };

    try {
        const resp = await fetch('{{ route('orders.calculate-tax') }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify(payload),
        });
        const result = await resp.json();
        result.forEach((t, i) => {
            if (this.lines[i]) {
                this.lines[i].tax_rate   = t.tax_rate;
                this.lines[i].tax_amount = t.tax_amount;
            }
        });
    } catch (_) {
        // Network error — leave existing values
    }
},
```

#### 5. Call `debounceTax()` on triggers

- `onCustomerChange()` — already added above
- When shipping address selection changes: `@change="shippingSelection = $event.target.value; debounceTax()"`
- `onProductChange(line)` — add `this.debounceTax()` at end
- Unit price input: `@input="debounceTax()"`

#### 6. Tax cells per line

Tax % stays hidden (submitted for record) and Tax $ is a **visible editable input** — AvaTax auto-fills it; admin can manually override:

```html
{{-- Tax % — hidden, still submitted for record --}}
<td style="display:none">
    <input type="number" :name="'lines[' + i + '][tax_rate]'" x-model="line.tax_rate"
           step="0.01" min="0">
</td>

{{-- Tax $ — editable; AvaTax auto-fills, admin can override --}}
<td class="px-2 py-2 w-28">
    <input type="number" :name="'lines[' + i + '][tax_amount]'" x-model="line.tax_amount"
           step="0.01" min="0"
           class="block w-full rounded border-gray-300 text-sm" />
</td>
```

#### 7. `addLine()` — include `tax_amount: 0`

```js
addLine() {
    this.lines.push({ product_listing_id: '', unit_price: '', tax_rate: 0, tax_amount: 0, sku: '', stock: '', stockLoading: false });
},
```

---

### `edit.blade.php` — changes

The edit form must match the create form: `<table>` layout, `onProductChange`, `loadStock`, editable tax input.

#### 1. PHP vars + JS bootstrap (before `x-data`)

```php
@php
    $listingsData = $productListings->map(fn($l) => [
        'id'  => $l->id,
        'name' => $l->product->name,
        'sku'  => $l->product->sku,
        'price' => (float) $l->price,
    ]);
    $linesData = $order->lines->map(fn($l) => [
        'product_listing_id' => $l->product_listing_id,
        'unit_price'         => (float) $l->unit_price,
        'tax_rate'           => (float) $l->tax_rate,
        'tax_amount'         => (float) $l->tax_amount,
        'sku'                => $l->productListing->product->sku ?? '',
        'serial_number'      => $l->inventorySerial?->serial_number,
        'stock'              => '',
        'stockLoading'       => false,
    ]);
    $feesData        = $order->orderFees->map(fn($f) => ['name' => $f->name, 'amount' => (float) $f->amount]);
    $editTaxExempt   = (bool) $order->customer?->tax_exempt;
    $editShipSnapshot = $order->shipping_address_line1 ? [
        'address_line1' => $order->shipping_address_line1,
        'city'          => $order->shipping_city,
        'state'         => $order->shipping_state,
        'postal_code'   => $order->shipping_postal_code,
        'country'       => $order->shipping_country,
    ] : null;
@endphp
<script>
    window.__orderListings      = @json($listingsData);
    window.__orderExistingLines = @json($linesData);
    window.__orderExistingFees  = @json($feesData);
    window.__editTaxExempt      = @json($editTaxExempt);
    window.__editShipSnapshot   = @json($editShipSnapshot);
    window.__editCustomerId     = @json($order->customer_id);
</script>
```

#### 2. Alpine `x-data` — full state

```js
x-data="{
    listings:         window.__orderListings,
    lines:            window.__orderExistingLines.map(l => ({ ...l })),
    fees:             window.__orderExistingFees.map(f => ({ ...f })),
    taxExempt:        window.__editTaxExempt ?? false,
    taxTimer:         null,
    shippingSnapshot: window.__editShipSnapshot ?? null,

    get subtotal() {
        return this.lines.reduce((s, l) =>
            s + parseFloat(l.unit_price || 0) + parseFloat(l.tax_amount || 0), 0);
    },
    get feesTotal() { return this.fees.reduce((s, f) => s + parseFloat(f.amount || 0), 0); },
    get grandTotal() {
        return this.subtotal + this.feesTotal
             + parseFloat(document.getElementById('shipping_edit')?.value || 0);
    },

    debounceTax() {
        clearTimeout(this.taxTimer);
        this.taxTimer = setTimeout(() => this.fetchAllLineTax(), 400);
    },
    async fetchAllLineTax() {
        const addr = this.shippingSnapshot;
        if (!addr || this.taxExempt) return;
        const payload = {
            customer_id:      window.__editCustomerId,
            shipping_address: addr,
            lines: this.lines.map(l => ({
                unit_price: parseFloat(l.unit_price) || 0,
                sku:        l.sku || '',
            })),
        };
        try {
            const resp = await fetch('/admin/orders/calculate-tax', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(payload),
            });
            const result = await resp.json();
            result.forEach((t, idx) => {
                if (this.lines[idx]) {
                    this.lines[idx].tax_rate   = t.tax_rate;
                    this.lines[idx].tax_amount = t.tax_amount;
                }
            });
        } catch (_) {}
    },

    onProductChange(line) {
        const listing = this.listings.find(l => l.id == line.product_listing_id);
        if (listing) {
            line.sku        = listing.sku;
            line.unit_price = listing.price;
            this.loadStock(line);
        }
        this.debounceTax();
    },
    async loadStock(line) {
        if (!line.product_listing_id) return;
        line.stockLoading = true;
        try {
            const resp = await fetch('/admin/orders/listing-stock/' + line.product_listing_id);
            const data = await resp.json();
            line.stock = data.stock.map(s => s.location + ': ' + s.qty).join(', ');
        } catch (_) {
            line.stock = '';
        } finally {
            line.stockLoading = false;
        }
    },

    addLine() {
        this.lines.push({
            product_listing_id: '', unit_price: 0, tax_rate: 0, tax_amount: 0,
            sku: '', stock: '', stockLoading: false, serial_number: null,
        });
    },
    removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
    addFee()       { this.fees.push({ name: '', amount: 0 }); },
    removeFee(i)   { this.fees.splice(i, 1); },
    fmt(v)         { return parseFloat(v || 0).toFixed(2); },
}"
```

#### 3. Line items — `<table>` layout (matches create form)

Replace the grid `<template x-for>` block with:

```html
<table class="min-w-full divide-y divide-gray-200">
    <thead>
        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <th class="px-4 py-3">Product</th>
            <th class="px-4 py-3">SKU</th>
            <th class="px-4 py-3">Stock</th>
            <th class="px-4 py-3">Unit Price</th>
            <th style="display:none">Tax %</th>
            <th class="px-4 py-3">Tax $</th>
            <th class="px-4 py-3 text-right">Subtotal</th>
            <th class="px-4 py-3"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        <template x-for="(line, i) in lines" :key="i">
            <tr>
                {{-- Product --}}
                <td class="px-4 py-3 w-56">
                    <select :name="'lines[' + i + '][product_listing_id]'"
                            x-model="line.product_listing_id"
                            @change="onProductChange(line)"
                            required
                            class="block w-full rounded border-gray-300 text-sm">
                        <option value="">Select…</option>
                        <template x-for="lst in listings" :key="lst.id">
                            <option :value="lst.id" x-text="lst.name"></option>
                        </template>
                    </select>
                </td>
                {{-- SKU --}}
                <td class="px-4 py-3 text-sm text-gray-600 w-28" x-text="line.sku"></td>
                {{-- Stock --}}
                <td class="px-4 py-3 text-xs text-gray-500 w-32">
                    <span x-show="line.stockLoading">…</span>
                    <span x-show="!line.stockLoading" x-text="line.stock || '—'"></span>
                    <span x-show="line.serial_number" class="block text-gray-400">
                        Serial: <span x-text="line.serial_number"></span>
                    </span>
                </td>
                {{-- Unit Price --}}
                <td class="px-4 py-3 w-28">
                    <input type="number" :name="'lines[' + i + '][unit_price]'"
                           x-model="line.unit_price"
                           @input="debounceTax()"
                           step="0.01" min="0" required
                           class="block w-full rounded border-gray-300 text-sm" />
                </td>
                {{-- Tax % — hidden, still submitted --}}
                <td style="display:none">
                    <input type="number" :name="'lines[' + i + '][tax_rate]'"
                           x-model="line.tax_rate" step="0.01" min="0">
                </td>
                {{-- Tax $ — editable; AvaTax auto-fills, admin can override --}}
                <td class="px-4 py-3 w-28">
                    <input type="number" :name="'lines[' + i + '][tax_amount]'"
                           x-model="line.tax_amount"
                           step="0.01" min="0"
                           class="block w-full rounded border-gray-300 text-sm" />
                </td>
                {{-- Subtotal --}}
                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900"
                    x-text="'$' + fmt(parseFloat(line.unit_price||0) + parseFloat(line.tax_amount||0))">
                </td>
                {{-- Remove --}}
                <td class="px-4 py-3">
                    <button type="button" @click="removeLine(i)"
                            :disabled="lines.length === 1"
                            class="rounded border border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50 disabled:opacity-40">
                        ×
                    </button>
                </td>
            </tr>
        </template>
    </tbody>
</table>
```

---

## REFACTOR

Nothing expected. The `calculateTax()` action is thin — all logic is in `AvaTaxService`.

---

## Design Notes

- `SalesOrder` type in AvaTax — estimate only, no committed transaction in AvaTax audit trail
- `tax_exempt` on Customer short-circuits any AvaTax call — zeros returned immediately
- Submitting `tax_amount` from form avoids server-side rounding drift (Gap 9)
- Tax rate column in table kept but hidden (`x-show="false"`) — still submitted for display on show page and line history
- Edit form uses shipping snapshot columns (`shipping_address_line1`, etc.) — not FK to address book
- Alpine debounces 400ms so rapid keystrokes don't flood the endpoint
- On network error in `fetchAllLineTax()`, existing tax values are preserved (silent no-op)
- `CustomerAddress` `state` column maps to AvaTax `region` — handled inside `AvaTaxService::calculateTax()`
