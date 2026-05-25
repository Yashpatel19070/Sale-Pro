# OrderService Integration — Spec

## Goal
Wire `AvaTaxService::calculateTax()` into `OrderService::create()` so that when an order
is saved, each `order_line` row gets the correct `tax_rate` and `tax_amount` from Avalara.

---

## Files to change

| File | Change |
|---|---|
| `app/Services/OrderService.php` | Inject `AvaTaxService`, pre-fetch serials, call AvaTax before DB transaction, pass tax data into `buildLines()` |
| `app/Http/Requests/Order/CreateOrderRequest.php` | `lines.*.tax_rate` → nullable |

**No changes to:** `Order` model, `OrderLine` model, any migration, any other method.

---

## `OrderService` — constructor

Add constructor to inject `AvaTaxService`:

```php
public function __construct(private readonly AvaTaxService $avatax) {}
```

Add import: `use App\Services\AvaTaxService;`

Container resolves `AvaTaxService` automatically via the singleton in `AppServiceProvider`.

---

## `OrderService::create()` — new sequence

The current `create()` opens `DB::transaction()` immediately on line 1.
**This must change.** AvaTax call MUST happen before the transaction opens.

### RULE: Never call AvaTax inside `DB::transaction()`
HTTP calls inside a DB transaction hold locks open. Always call AvaTax first, then open the transaction.

### New sequence inside `create()`:

**Step 1 — Pre-fetch serials (outside transaction)**

Query serials ONCE here. Reused for both the AvaTax call and `buildLines()`.
This replaces the serial query that currently happens inside `buildLines()`.

```
$serials = []
foreach $data['lines'] as $i => $line:
    $serials[$i] = InventorySerial::with('product')->findOrFail($line['serial_id'])
```

**Step 2 — Build line summaries for AvaTax (outside transaction)**

```
$lineSummaries = []
foreach $data['lines'] as $i => $line:
    $lineSummaries[$i] = [
        'unit_price' => (float) $line['unit_price'],
        'sku'        => $serials[$i]->product->sku ?? '',
    ]
```

**Step 3 — Extract ShipTo address (outside transaction)**

Read from `$data['shipping']` — NOT `$data['address']`.

```
$shipTo = $this->extractShipTo($data)
```

See `extractShipTo()` spec below.

**Step 4 — Call AvaTax (outside transaction)**

```
$taxData = $this->avatax->calculateTax($lineSummaries, $shipTo)
```

If this throws — exception bubbles up, DB transaction never opens, order is not saved.

**Step 5 — Open DB::transaction() and proceed as before**

Pass `$taxData` and `$serials` into `buildLines()`:

```
return DB::transaction(function () use ($data, $user, $taxData, $serials) {
    ...
    $lineRows = $this->buildLines($data['lines'], $taxData, $serials)
    $subtotal = array_sum(array_column($lineRows, 'line_total'))
    ...
    // Everything else unchanged
})
```

---

## New private helper: `extractShipTo(array $data): array`

Reads from `$data['shipping']` — this is the key the current `OrderService` uses (not `$data['address']`).

```
$addr = $data['shipping'] ?? []

Case 1 — existing address by ID:
    if $addr['address_id'] is set and not empty:
        $ca = CustomerAddress::find($addr['address_id'])
        if $ca exists:
            return [
                'line1'       => $ca->address_line1,   ← model field is address_line1
                'city'        => $ca->city,
                'state'       => $ca->state,
                'postal_code' => $ca->postal_code,
                'country'     => $ca->country,
            ]

Case 2 — inline address fields:
    if $addr['line1'] is set and not empty:
        return [
            'line1'       => $addr['line1'],
            'city'        => $addr['city'],
            'state'       => $addr['state'],
            'postal_code' => $addr['postal_code'],
            'country'     => $addr['country'],
        ]

Case 3 — walk-in, no delivery address:
    return []
```

IMPORTANT field naming:
- `CustomerAddress` model column = `address_line1`
- ShipTo array key = `line1`
These are different. Map explicitly as shown above.

---

## `buildLines()` — updated signature

Current:
```php
private function buildLines(array $lines): array
```

New:
```php
private function buildLines(array $lines, array $taxData, array $serials): array
```

### What changes inside the loop

First — change the loop declaration to include `$i`:
```
// REMOVE:
foreach ($lines as $line)

// USE:
foreach ($lines as $i => $line)   ← $i required to index into $serials and $taxData
```

Remove the serial DB query — use pre-fetched `$serials[$i]` instead:
```
// REMOVE this line:
$serial = InventorySerial::with('product')->findOrFail($line['serial_id'])

// USE this instead:
$serial = $serials[$i]
```

Remove manual tax calculation — use AvaTax data instead:
```
// REMOVE:
$taxRate   = (float) $line['tax_rate']
$taxAmount = round($unitPrice * $taxRate, 2)

// USE:
$taxRate   = (float) ($taxData[$i]['tax_rate']   ?? 0.0)
$taxAmount = (float) ($taxData[$i]['tax_amount'] ?? 0.0)
```

Everything else in `buildLines()` stays identical:
- `inventory_serial_id`, `sku`, `product_name`, `unit_price` — unchanged
- `line_total = round($unitPrice + $taxAmount, 2)` — unchanged

---

## `subtotal` and `grand_total` — DO NOT CHANGE

```
$subtotal    = array_sum(array_column($lineRows, 'line_total'))  // unchanged
'grand_total' => $subtotal + $feeTotal + $shipping               // unchanged
```

`line_total` already includes `tax_amount` — so `grand_total` is already correct.
Do not add a `tax` column to orders. Do not change the formula.

---

## `CreateOrderRequest` — `lines.*.tax_rate` → nullable

```php
// Before
'lines.*.tax_rate' => ['required', 'numeric', 'min:0'],

// After
'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
```

AvaTax provides the tax rate now. The caller no longer needs to send it.

---

## Rules

1. AvaTax call is always OUTSIDE `DB::transaction()` — no exceptions
2. Serials are fetched ONCE — before the transaction — and passed into `buildLines()`
3. `buildLines()` does NOT query DB for serials — uses pre-fetched `$serials` array
4. `extractShipTo()` reads from `$data['shipping']` — not `$data['address']`
5. `CustomerAddress` model field is `address_line1` — map to `'line1'` key in shipTo array
6. `subtotal` and `grand_total` formulas are unchanged
7. No changes to `Order` model `$fillable` or `casts()`
8. `$taxData` and `$serials` are 0-based arrays — indexes must match `$data['lines']`

---

## What is NOT in scope

- `commitTransaction` — future phase, payment module
- `voidTransaction` — future phase, cancel flow
- `recordCashPayment`, `ship`, `markDelivered` — no changes
- `Order` model — no changes
- Any migration — no changes
