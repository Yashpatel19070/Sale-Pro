# AvaTax — Phase 2: Tax Calculation

## Files
| File | Action |
|------|--------|
| `app/Services/AvaTaxService.php` | Add `calculateTax()` method |
| `tests/Unit/AvaTaxServiceTest.php` | Add unit tests |

---

## RED — Write tests first

### `tests/Unit/AvaTaxServiceTest.php` — add these tests

```php
it('calculateTax_returns_tax_per_line_for_valid_address', function () {
    $service = new AvaTaxService(config('avatax'));

    $lines  = [['sku' => 'PROD-A', 'unit_price' => 100.00, 'quantity' => 1]];
    $shipTo = [
        'address_line1' => '5426 N Shepherd Dr',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77091',
        'country'       => 'US',
    ];

    $result = $service->calculateTax($lines, $shipTo, 'CUST-001');

    expect($result[0]['tax_rate'])->toBeGreaterThan(0)->toBeLessThan(100);
    expect($result[0]['tax_amount'])->toBeGreaterThan(0);
    expect($result[0])->not->toHaveKey('license_key');
})->group('integration')->skip(empty(env('AVATAX_ACCOUNT_NUMBER')), 'AVATAX_ACCOUNT_NUMBER not set.');

it('calculateTax_returns_zeros_for_all_lines_when_disabled', function () {
    $config  = [...config('avatax'), 'enabled' => false];
    $service = new AvaTaxService($config);

    $lines = [
        ['sku' => 'A', 'unit_price' => 100.00, 'quantity' => 1],
        ['sku' => 'B', 'unit_price' => 50.00,  'quantity' => 1],
    ];

    $result = $service->calculateTax($lines, [], 'CUST-001');

    expect($result[0])->toBe(['tax_rate' => 0, 'tax_amount' => 0]);
    expect($result[1])->toBe(['tax_rate' => 0, 'tax_amount' => 0]);
});

it('calculateTax_returns_zero_for_line_with_zero_price', function () {
    $service = new AvaTaxService(config('avatax'));

    $lines  = [['sku' => 'PROD-A', 'unit_price' => 0, 'quantity' => 1]];
    $shipTo = [
        'address_line1' => '5426 N Shepherd Dr',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77091',
        'country'       => 'US',
    ];

    $result = $service->calculateTax($lines, $shipTo, 'CUST-001');

    expect($result[0])->toBe(['tax_rate' => 0, 'tax_amount' => 0]);
});

it('calculateTax_returns_zeros_for_all_lines_on_sdk_exception', function () {
    $config  = [...config('avatax'), 'account' => 'bad', 'license_key' => 'bad'];
    $service = new AvaTaxService($config);

    $lines  = [['sku' => 'A', 'unit_price' => 100.00, 'quantity' => 1]];
    $shipTo = [
        'address_line1' => '5426 N Shepherd Dr',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77091',
        'country'       => 'US',
    ];

    $result = $service->calculateTax($lines, $shipTo, 'CUST-001');

    expect($result[0])->toBe(['tax_rate' => 0, 'tax_amount' => 0]);
});

it('calculateTax_rate_is_percentage_not_decimal_fraction', function () {
    $service = new AvaTaxService(config('avatax'));

    $lines  = [['sku' => 'PROD-A', 'unit_price' => 100.00, 'quantity' => 1]];
    $shipTo = [
        'address_line1' => '5426 N Shepherd Dr',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77091',
        'country'       => 'US',
    ];

    $result = $service->calculateTax($lines, $shipTo, 'CUST-001');

    // Rate must be percentage e.g. 8.25, NOT decimal fraction 0.0825
    expect($result[0]['tax_rate'])->toBeGreaterThan(1);
})->group('integration')->skip(empty(env('AVATAX_ACCOUNT_NUMBER')), 'AVATAX_ACCOUNT_NUMBER not set.');

it('calculateTax_handles_multiple_lines_correctly', function () {
    $service = new AvaTaxService(config('avatax'));

    $lines = [
        ['sku' => 'PROD-A', 'unit_price' => 100.00, 'quantity' => 1],
        ['sku' => 'PROD-B', 'unit_price' => 50.00,  'quantity' => 1],
    ];
    $shipTo = [
        'address_line1' => '5426 N Shepherd Dr',
        'city'          => 'Houston',
        'state'         => 'TX',
        'postal_code'   => '77091',
        'country'       => 'US',
    ];

    $result = $service->calculateTax($lines, $shipTo, 'CUST-001');

    expect($result)->toHaveCount(2);
    expect($result[0])->toHaveKeys(['tax_rate', 'tax_amount']);
    expect($result[1])->toHaveKeys(['tax_rate', 'tax_amount']);
    expect($result[0])->not->toHaveKey('license_key');
    expect($result[1])->not->toHaveKey('license_key');
})->group('integration')->skip(empty(env('AVATAX_ACCOUNT_NUMBER')), 'AVATAX_ACCOUNT_NUMBER not set.');
```

---

## GREEN — Implement

### `AvaTaxService::calculateTax()` signature
```php
public function calculateTax(array $lines, array $shipTo, string $customerCode): array
```

### Logic
1. `!isEnabled()` → return zeros for all lines immediately
2. Build zeros result array upfront (one entry per line)
3. Filter out lines with `unit_price <= 0` — keep their index, return zeros for them
4. Build AvaTax transaction:
   - `type`: `SalesOrder` — estimate only, no audit trail committed in AvaTax
   - `companyCode`: `config('avatax.company_code')`
   - `date`: `now()->toDateString()`
   - `customerCode`: `$customerCode`
   - `addresses.ShipFrom`: from `config('avatax.ship_from')`
     - `line1 → street, city → city, region → state, postalCode → zip, country → country`
   - `addresses.ShipTo`:
     - `line1 → address_line1, city → city, region → state, postalCode → postal_code, country → country`
   - `lines[]`: each valid line → `{number: i+1, amount: unit_price, quantity: 1, itemCode: sku}`
5. Call `$this->makeClient()->createTransaction('', $model)`
6. Per response line: extract `$responseLine->taxCalculated` → `$taxAmount`
7. Derive rate: `round(($taxAmount / $unitPrice) * 100, 4)` — stored as percentage e.g. `8.2500`
8. Map results back to original line indexes
9. Return `[['tax_rate' => rate, 'tax_amount' => taxAmount], ...]` — same order/count as input
10. Entire call in try/catch → return all zeros on any `\Throwable`
11. `license_key` never appears in return value

---

## REFACTOR
Nothing expected.

---

## Design Notes
- `quantity` is always `1` per line — each order line maps to one serial unit
- `SalesOrder` type is used (not `SalesInvoice`) so AvaTax does not commit a transaction record
- Rate derived as percentage: `(taxAmount / unitPrice) * 100` — matches `decimal(6,4)` column (`max:100` validation still holds)
- `makeClient()` is reused from Phase 1 — no changes needed
- Bad credentials → SDK throws on `createTransaction()` → caught → zeros returned
