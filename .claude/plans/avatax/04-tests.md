# AvaTax Integration — Tests

---

## Rule

`AVATAX_ENABLED=false` in `.env.testing` — tests NEVER hit the Avalara sandbox.
`AvaTaxService` is mocked at the unit/service level; `OrderControllerTest` runs in
fallback mode (passthrough). No Avalara credentials needed in CI.

---

## 1. `tests/Unit/AvaTaxServiceTest.php` (new)

### Setup

```php
beforeEach(function () {
    $this->config = [
        'enabled'      => true,
        'account_number' => 'TEST_ACCT',
        'license_key'  => 'TEST_KEY',
        'company_code' => 'DEFAULT',
        'environment'  => 'sandbox',
        'ship_from'    => [
            'street'  => '100 Main St',
            'city'    => 'Austin',
            'state'   => 'TX',
            'zip'     => '78701',
            'country' => 'US',
        ],
    ];
});
```

### Tests

```
it('returns tax data per line when enabled')
it('returns fallback tax data when disabled')
it('returns zero tax for empty lines')
it('throws RuntimeException when AvaTax API returns error')
it('commitTransaction is a no-op when disabled')
it('voidTransaction is a no-op when disabled')
```

**Mocking strategy:** Use `\Mockery::mock(AvaTaxClient::class)` or mock at the HTTP level
via Guzzle `MockHandler`. Inject the mock into a `AvaTaxService` subclass that overrides
`makeClient()` to return the mock.

Example — mock the AvaTaxClient directly:

```php
it('returns tax data per line when enabled', function () {
    // Build a fake TransactionModel stdClass
    $fakeLine         = new stdClass();
    $fakeLine->lineNumber = '1';
    $fakeLine->tax    = 8.25;
    $fakeDetail       = new stdClass();
    $fakeDetail->rate = 0.0825;
    $fakeLine->details = [$fakeDetail];

    $fakeTransaction           = new stdClass();
    $fakeTransaction->totalTax = 8.25;
    $fakeTransaction->lines    = [$fakeLine];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('withSecurity')->andReturnSelf();
    $mockClient->shouldReceive('createTransaction')->andReturn($fakeTransaction);

    $service = new class($this->config, $mockClient) extends AvaTaxService {
        public function __construct(array $config, private AvaTaxClient $mockClient)
        {
            parent::__construct($config);
        }
        protected function makeClient(): AvaTaxClient { return $this->mockClient; }
    };

    $lines  = [['serial_id' => 1, 'sku' => 'SKU-001', 'unit_price' => 100.00, 'tax_rate' => null]];
    $shipTo = ['line1' => '100 Oak St', 'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75201', 'country' => 'US'];

    $result = $service->calculateTax($lines, $shipTo, 'ORD-TEST');

    expect($result[0]['tax_amount'])->toBe(8.25)
        ->and($result[0]['tax_rate'])->toBe(0.0825)
        ->and($result['_total_tax'])->toBe(8.25);
});
```

---

## 2. `tests/Unit/OrderServiceTest.php` — updates

### Mock AvaTaxService

Replace all `new OrderService()` instantiation with mocked version:

```php
beforeEach(function () {
    $this->avatax = Mockery::mock(AvaTaxService::class);

    // Default happy-path behaviour — zero tax (fallback)
    $this->avatax->shouldReceive('calculateTax')->andReturn([
        0 => ['tax_rate' => 0.0, 'tax_amount' => 0.0],
        '_total_tax' => 0.0,
    ]);
    $this->avatax->shouldReceive('commitTransaction')->andReturn(null);
    $this->avatax->shouldReceive('voidTransaction')->andReturn(null);

    $this->service = new OrderService($this->avatax);
});
```

### New assertions

After `create()`:
```php
expect($order->tax)->toBe('0.00')
    ->and($order->subtotal)->toBe('100.00')        // pre-tax only
    ->and($order->grand_total)->toBe('100.00');    // subtotal + 0 tax + 0 fees + 0 shipping
```

After `recordCashPayment()`:
```php
$this->avatax->shouldReceive('commitTransaction')
    ->once()
    ->with($order->number);
```

---

## 3. `tests/Feature/OrderControllerTest.php` — updates

Run with `AVATAX_ENABLED=false` (already the `.env.testing` default).

Update `lines` payload to omit `tax_rate` or send it as `null`:
```php
'lines' => [
    ['serial_id' => $serial->id, 'unit_price' => 100.00],  // no tax_rate — nullable now
],
```

Assert `tax` column:
```php
$this->assertDatabaseHas('orders', [
    'number' => $order->number,
    'tax'    => '0.00',
]);
```

---

## Coverage targets

| File | Target |
|------|--------|
| `AvaTaxService` | 90%+ |
| `OrderService` (updated) | maintain existing coverage |
| `OrderController` (updated) | maintain existing coverage |
