# AvaTax — Phase 3: Order Integration (Commit on Payment)

## Goal
Commit a `SalesInvoice` to AvaTax when a payment is recorded, so the transaction lands in Avalara's ledger and shows up in tax-filing reports. Phase 2's `calculateTax()` remains a `SalesOrder` estimate (uncommitted) used at order placement for quoting.

## Files
| File | Action |
|------|--------|
| `app/Services/AvaTaxService.php` | Add `commitInvoice()`; widen `makeClient()` to `protected` (testability) |
| `app/Services/OrderService.php` | Inject `AvaTaxService`; call `commitInvoice()` after `DB::transaction()` in `recordCashPayment()` |
| `tests/Unit/AvaTaxServiceTest.php` | Add commitInvoice guard + happy-path tests |

---

## RED — Write tests first

### `tests/Unit/AvaTaxServiceTest.php` — add these tests

```php
use Avalara\AvaTaxClient;

afterEach(fn () => Mockery::close());

it('commitInvoice_returns_false_when_disabled', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'enabled' => false]);

    $result = $service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('commitInvoice_returns_false_when_ship_from_incomplete', function () use ($minimalConfig) {
    $config = [
        ...$minimalConfig,
        'enabled' => true,
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'FAKE',
        'ship_from' => [
            'street' => null,
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $service = new AvaTaxService($config);

    $result = $service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75001', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('commitInvoice_returns_false_when_all_lines_have_zero_price', function () use ($minimalConfig) {
    $config = [
        ...$minimalConfig,
        'enabled' => true,
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'FAKE',
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $service = new AvaTaxService($config);

    $result = $service->commitInvoice(
        [['unit_price' => 0.0, 'sku' => 'FREE']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('commitInvoice_sends_SalesInvoice_with_commit_true_and_order_number_as_code', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => null,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->once()
        ->with('', Mockery::on(function ($model) {
            return $model->type === 'SalesInvoice'
                && $model->commit === true
                && $model->code === 'ORD-2026-0019'
                && $model->companyCode === 'TESTCO'
                && $model->customerCode === '19'
                && count($model->lines) === 2
                && $model->lines[0]->amount === 200.0
                && $model->lines[0]->itemCode === 'ECM-2024'
                && $model->lines[1]->amount === 40.0
                && $model->lines[1]->itemCode === 'FEE-Programming'
                && $model->addresses->shipFrom->city === 'Houston'
                && $model->addresses->shipTo->city === 'Houston';
        }))
        ->andReturn((object) ['lines' => []]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->commitInvoice(
        [
            ['unit_price' => 200.0, 'sku' => 'ECM-2024'],
            ['unit_price' => 40.0, 'sku' => 'FEE-Programming'],
        ],
        ['address_line1' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '19',
        'ORD-2026-0019',
    );

    expect($result)->toBeTrue();
});
```

---

## GREEN — Implement

### `AvaTaxService::commitInvoice()` signature

```php
public function commitInvoice(array $lines, array $shipTo, string $customerCode, string $documentCode): bool
```

### Logic
1. `!isEnabled()` → return `false` immediately
2. `ship_from` missing `street`/`city`/`state`/`zip` → return `false`
3. Build `LineItemModel[]` from `$lines`, skipping `unit_price <= 0`
4. If no valid lines → return `false`
5. Build `AddressLocationInfo` for `shipFrom` (from config) and `shipTo` (from arg)
6. Build `CreateTransactionModel`:
   - `type = 'SalesInvoice'` (committed, not `SalesOrder`)
   - `commit = true`
   - `code = $documentCode` (the order number — lets future void/adjust calls reference this doc)
   - `companyCode`, `customerCode`, `date = now()->toDateString()`, `addresses`, `lines`
7. Call `$this->makeClient()->createTransaction('', $model)`
8. Return `true` on success
9. Entire call wrapped in try/catch → log warning + return `false` on any `\Throwable`

### `AvaTaxService::makeClient()` visibility
Widen from `private` → `protected` so test doubles can override it to return a mocked `AvaTaxClient`. No runtime behavior change.

### `OrderService` constructor — inject `AvaTaxService`
```php
public function __construct(
    private readonly InventoryMovementService $movements,
    private readonly AvaTaxService $avaTax,
) {}
```

### `OrderService::recordCashPayment()` — commit AFTER the DB transaction

Capture the payment from the transaction, then call AvaTax outside the transaction. **Critical:** synchronous best-effort. A slow or failed AvaTax call must NOT roll back the recorded payment.

```php
$payment = DB::transaction(function () use ($order, $data, $createdBy): Payment {
    // ... existing payment + status + event + activity logic ...
    return $payment;
});

$order->loadMissing('lines.lineFees');
$taxLines = [];
foreach ($order->lines as $line) {
    $taxLines[] = ['unit_price' => (float) $line->unit_price, 'sku' => (string) $line->sku];
    foreach ($line->lineFees as $fee) {
        $taxLines[] = ['unit_price' => (float) $fee->amount, 'sku' => 'FEE-'.$fee->name];
    }
}

$shipTo = [
    'address_line1' => $order->shipping_address_line1 ?: $order->billing_address_line1,
    'city' => $order->shipping_city ?: $order->billing_city,
    'state' => $order->shipping_state ?: $order->billing_state,
    'postal_code' => $order->shipping_postal_code ?: $order->billing_postal_code,
    'country' => $order->shipping_country ?: $order->billing_country,
];

$this->avaTax->commitInvoice($taxLines, $shipTo, (string) $order->customer_id, $order->number);

return $payment;
```

---

## REFACTOR
Nothing expected. Two methods (`calculateTax` + `commitInvoice`) share line/address construction — duplication accepted for clarity. Extract a private helper only if a third caller appears.

---

## Design Notes

| Decision | Rationale |
|----------|-----------|
| `SalesInvoice` + `commit=true` (not `SalesOrder`) | Records the transaction in AvaTax's ledger for tax-filing reports. `SalesOrder` is quote-only. |
| `code = $order->number` | Lets future void / adjust / refund calls reference the same AvaTax document by the order number. |
| Commit on **payment**, not on order placement | An unpaid order is not a sale. Filing happens against payments. Matches the order's `payment_status → paid` transition. |
| **Synchronous, after** `DB::transaction()` | Simple — no queue infra. Outside the transaction so AvaTax latency doesn't hold a row lock, and an AvaTax failure doesn't roll back a recorded payment. |
| Best-effort (returns `bool`, never throws) | Tax-filing reliability is a downstream concern. A transient AvaTax outage must not break payment recording. Failures log a warning with `document_code` + `customer_code` + `line_count` for retry. |
| Lines include **per-line fees** | Phase 2 already taxed fees individually. Each fee becomes an AvaTax line with `sku = 'FEE-<name>'` so filings reflect the actual taxed amounts. |
| `shipTo = shipping snapshot ?? billing snapshot` | Walk-in pickup orders (ex-19) have NULL shipping snapshot — fall back to billing (which holds the shop address for cash sales). Shipped orders use shipping snapshot directly. |
| `makeClient()` widened to `protected` | Lets test doubles inject a mocked `AvaTaxClient`. No runtime impact. |

---

## Out of Scope

**Deferred to v2 (product decision, 2026-05-27):**
- **Void on cancel** — when an order is cancelled, would call AvaTax `voidTransaction(companyCode, code)` to reverse the committed invoice.
- **Adjust on partial refund** — when a return/refund is recorded, would call AvaTax `adjustTransaction()` to net out the refunded portion.

**Still open (minor):**
- **Retry queue for commitInvoice failures** — currently synchronous best-effort, failures logged only. Not blocking v1 since AvaTax sandbox sees ~99% uptime.
- **Reconciliation report** — nightly check comparing local `payments` rows against AvaTax `listTransactions()` to flag drifts. Defer until void/adjust land.

---

## Tests

```bash
php artisan test tests/Unit/AvaTaxServiceTest.php --filter=commitInvoice
```

Expected: **4 passed** (3 guard tests + 1 happy-path mocked test).
