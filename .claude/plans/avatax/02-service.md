# AvaTaxService — Spec

## File
`app/Services/AvaTaxService.php`

## Purpose
Single-responsibility wrapper around `avalara/avataxclient`.
One job only: fetch correct tax rate and tax amount per line item from Avalara AvaTax.
No DB calls. No fallback. No commit. No void. Just tax calculation.

---

## Config reference

All values come from `config('services.avatax')`. No hardcoded values in this class.

| Config key | Env var | Example |
|---|---|---|
| `account_number` | `AVATAX_ACCOUNT_NUMBER` | `1234567890` |
| `license_key` | `AVATAX_LICENSE_KEY` | `ABCDEF...` |
| `company_code` | `AVATAX_COMPANY_CODE` | `DEFAULT` |
| `environment` | `AVATAX_ENVIRONMENT` | `sandbox` or `production` |
| `tax_code` | `AVATAX_TAX_CODE` | `P0000000` |
| `ship_from.street` | `AVATAX_SHIP_FROM_STREET` | `100 Main St` |
| `ship_from.city` | `AVATAX_SHIP_FROM_CITY` | `Austin` |
| `ship_from.state` | `AVATAX_SHIP_FROM_STATE` | `TX` |
| `ship_from.zip` | `AVATAX_SHIP_FROM_ZIP` | `78701` |
| `ship_from.country` | `AVATAX_SHIP_FROM_COUNTRY` | `US` |

---

## Constructor

```php
public function __construct(private readonly array $config)
```

Receives the full `config('services.avatax')` array.
Bound as singleton in `AppServiceProvider::register()` — see binding section below.

---

## Method: `calculateTax(array $lines, array $shipTo): array`

### Signature
```php
public function calculateTax(array $lines, array $shipTo): array
```

No `$orderRef` parameter — `C_SALESORDER` estimates are ephemeral, no transaction code needed.

---

### `$lines` — input shape

Pre-fetched by `OrderService` before calling this method. Each element:

```
[
    'unit_price' => float,   // price of one unit
    'sku'        => string,  // product SKU — used as itemCode in Avalara
]
```

Array is 0-based. Order must be preserved — response maps back by same index.

---

### `$shipTo` — input shape

Delivery address for the customer. Pass empty array `[]` for walk-in orders (no delivery).

```
[
    'line1'       => string,  // street address
    'city'        => string,
    'state'       => string,  // 2-letter e.g. 'TX'
    'postal_code' => string,
    'country'     => string,  // 2-letter ISO e.g. 'US'
]
```

If `$shipTo` is empty — skip `withAddress('ShipTo')` entirely. Avalara will use ShipFrom rates.

---

### Return shape

0-based array matching `$lines` input order:

```
[
    0 => ['tax_rate' => 0.0825, 'tax_amount' => 8.25],
    1 => ['tax_rate' => 0.0825, 'tax_amount' => 20.63],
]
```

- `tax_rate` — rounded to 4 decimal places (e.g. `0.0825` = 8.25%)
- `tax_amount` — rounded to 2 decimal places

---

### SDK call sequence — step by step

**Step 1 — Build client**
```
$client = new AvaTaxClient('sale-pro', '1.0', gethostname(), $this->config['environment'])
$client->withSecurity($this->config['account_number'], $this->config['license_key'])
```

**Step 2 — Build TransactionBuilder**

IMPORTANT — constructor signature is: `($client, $companyCode, $type, $customerCode)`

- 4th param is `$customerCode` — pass static string `'sale-pro'`
- Document type MUST be `DocumentType::C_SALESORDER` — estimate only, nothing stored in Avalara
- Do NOT use `C_SALESINVOICE` here
- Do NOT call `->withTransactionCode()` — not needed for estimates

```
$tb = new TransactionBuilder(
    $client,
    $this->config['company_code'],
    DocumentType::C_SALESORDER,
    'sale-pro'
)
```

**Step 3 — Set ShipFrom address (always)**
```
$tb->withAddress(
    'ShipFrom',
    $this->config['ship_from']['street'], null, null,
    $this->config['ship_from']['city'],
    $this->config['ship_from']['state'],
    $this->config['ship_from']['zip'],
    $this->config['ship_from']['country']
)
```

**Step 4 — Set ShipTo address (only if $shipTo is not empty)**
```
if $shipTo is not empty:
    $tb->withAddress(
        'ShipTo',
        $shipTo['line1'], null, null,
        $shipTo['city'],
        $shipTo['state'],
        $shipTo['postal_code'],
        $shipTo['country']
    )
```

**Step 5 — Add one line per item**

Line numbers are 1-based strings: `'1'`, `'2'`, `'3'`…

```
foreach $lines as $i => $line:
    $tb->withLine(
        (float) $line['unit_price'],   // amount
        1,                              // quantity — always 1 (one serial per line)
        $line['sku'],                   // itemCode
        $this->config['tax_code'],      // taxCode — from config, never hardcoded
        (string) ($i + 1)              // lineNumber — 1-based string
    )
```

**Step 6 — Execute**
```
$transaction = $tb->create()   // returns TransactionModel (stdClass)
```

**Step 7 — Map response to result array**

`$transaction->lines` is an array of line objects.
`$txLine->lineNumber` is a 1-based string — subtract 1 for 0-based array index.
`$txLine->tax` is the tax amount for that line.
`$txLine->details[0]->rate` is the effective rate — may be missing if tax is 0, default to `0.0`.

```
foreach ($transaction->lines ?? []) as $txLine:   ← guard against null lines
    $idx = (int) $txLine->lineNumber - 1
    $result[$idx] = [
        'tax_rate'   => round((float) ($txLine->details[0]?->rate ?? 0.0), 4),  ← null-safe ?-> required
        'tax_amount' => round((float) $txLine->tax, 2),
    ]

return $result
```

---

### Error handling

Wrap `$tb->create()` in `try/catch(\Exception $e)`.
On failure: throw `new \RuntimeException('AvaTax calculateTax failed: ' . $e->getMessage())`.
Exception bubbles up to `OrderService::create()` before the DB transaction opens — order is NOT saved.

---

## Rules

1. `strict_types=1` on the file
2. No hardcoded values — tax code, addresses, credentials all from `$this->config`
3. `C_SALESORDER` only in this method — never `C_SALESINVOICE`
4. No DB calls — this class never touches Eloquent or DB facades
5. Quantity is always `1` — the project model is one serial per line
6. `ShipTo` is optional — skip `withAddress('ShipTo')` when `$shipTo` is empty array
7. `$customerCode` in TransactionBuilder is `'sale-pro'` (static) — not the order number
8. No `->withTransactionCode()` call — not needed for `C_SALESORDER` estimates
9. Throw `\RuntimeException` on any API failure — never swallow errors silently

---

## AppServiceProvider binding

In `register()` — NOT `boot()`:

```php
$this->app->singleton(AvaTaxService::class, function () {
    return new AvaTaxService(config('services.avatax'));
});
```

Add import: `use App\Services\AvaTaxService;`

---

## Config changes required before writing this service

**`config/services.php`** — add `tax_code` inside the existing `avatax` array:
```
'tax_code' => env('AVATAX_TAX_CODE', 'P0000000'),
```

**`.env.example`** — add under the AvaTax section:
```
AVATAX_TAX_CODE=
```
