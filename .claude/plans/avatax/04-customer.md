# AvaTax — Phase 4: Customer Lifecycle (Queued Sync)

## Goal
When a Customer is created or updated locally, dispatch a queued job that registers (or updates) the customer record in AvaTax. This is the prerequisite for exemption certificates and tax-ID handling to work in `commitInvoice()` (Phase 3).

## Why a queue (not synchronous)
- Customer create/update completes instantly — admin form never blocks on a slow AvaTax call.
- Built-in retries (`$tries = 3`, exponential backoff) absorb transient AvaTax outages.
- Failure surfaces in `failed_jobs` table for ops review.
- **Acknowledged race window:** there is a brief period (seconds, sometimes longer if queue is backed up) where the local Customer row exists but `avatax_customer_id` is still NULL. If `commitInvoice()` fires in that window, AvaTax accepts `customerCode` as a free-text label — exemption certs won't auto-apply. Mitigated by running `php artisan queue:work` continuously; revisit if it bites in practice.

## Files
| File | Action |
|------|--------|
| `database/migrations/2026_05_27_xxxxxx_add_avatax_fields_to_customers_table.php` | NEW — add 5 columns |
| `app/Models/Customer.php` | add 5 fillable + cast for `avatax_synced_at` |
| `app/Services/AvaTaxService.php` | add `upsertCustomer()` |
| `app/Jobs/SyncCustomerToAvaTaxJob.php` | NEW |
| `app/Services/CustomerService.php` | dispatch job in `store()` + `update()` |
| `tests/Unit/AvaTaxServiceTest.php` | 2 new tests |
| `tests/Unit/Jobs/SyncCustomerToAvaTaxJobTest.php` | NEW, 2 tests |
| `tests/Unit/CustomerServiceTest.php` | 2 new tests |

---

## Schema

```php
Schema::table('customers', function (Blueprint $t) {
    $t->string('avatax_customer_id')->nullable()->after('tax_exempt');
    $t->string('tax_identification_number')->nullable()->after('avatax_customer_id');
    $t->string('exemption_certificate_number')->nullable()->after('tax_identification_number');
    $t->string('entity_use_code', 2)->nullable()->after('exemption_certificate_number');
    $t->timestamp('avatax_synced_at')->nullable()->after('entity_use_code');
});
```

| Column | Purpose |
|--------|---------|
| `avatax_customer_id` | AvaTax-side customer code returned by `createCustomers()` |
| `tax_identification_number` | VAT / GST / federal EIN / resale cert # |
| `exemption_certificate_number` | exemption cert reference |
| `entity_use_code` | AvaTax category: A=fed gov, B=state gov, E=charity, G=resale, etc. |
| `avatax_synced_at` | last successful sync timestamp; NULL = never synced |

---

## RED — Tests first

### `tests/Unit/AvaTaxServiceTest.php` — append

```php
it('upsertCustomer_sends_correct_payload_to_avatax', function () {
    $config = [...validEnabledConfig...];
    $customer = Customer::factory()->make([
        'id' => 42,
        'name' => 'Acme Resale Inc',
        'email' => 'ar@example.com',
        'tax_identification_number' => 'TX-RESALE-99',
        'entity_use_code' => 'G',
        'tax_exempt' => true,
    ]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCustomers')
        ->once()
        ->with($config['company_id'], Mockery::on(function ($payload) {
            return is_array($payload)
                && $payload[0]->customerCode === '42'
                && $payload[0]->name === 'Acme Resale Inc'
                && $payload[0]->emailAddress === 'ar@example.com'
                && $payload[0]->taxIdentificationNumber === 'TX-RESALE-99'
                && $payload[0]->entityUseCode === 'G';
        }))
        ->andReturn((object) ['value' => [(object) ['customerCode' => '42']]]);

    $service = new class($config, $mockClient) extends AvaTaxService {
        public function __construct(array $c, private $client) { parent::__construct($c); }
        protected function makeClient(): AvaTaxClient { return $this->client; }
    };

    expect($service->upsertCustomer($customer))->toBe('42');
});

it('upsertCustomer_returns_null_on_sdk_failure', function () {
    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCustomers')->andThrow(new \RuntimeException('boom'));

    $service = new class([...validEnabledConfig...], $mockClient) extends AvaTaxService { ... };

    expect($service->upsertCustomer(Customer::factory()->make()))->toBeNull();
});
```

### `tests/Unit/Jobs/SyncCustomerToAvaTaxJobTest.php` — NEW

```php
it('stores avatax_customer_id and avatax_synced_at on success', function () {
    $customer = Customer::factory()->create(['avatax_customer_id' => null]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')->once()->andReturn('AVATAX-CODE-X');

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    expect($customer->fresh()->avatax_customer_id)->toBe('AVATAX-CODE-X');
    expect($customer->fresh()->avatax_synced_at)->not->toBeNull();
});

it('leaves avatax_customer_id null on failure', function () {
    $customer = Customer::factory()->create(['avatax_customer_id' => null]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')->once()->andReturn(null);

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    expect($customer->fresh()->avatax_customer_id)->toBeNull();
    expect($customer->fresh()->avatax_synced_at)->toBeNull();
});
```

### `tests/Unit/CustomerServiceTest.php` — append

```php
it('dispatches SyncCustomerToAvaTaxJob on store', function () {
    Queue::fake();
    app(CustomerService::class)->store([...valid payload...]);
    Queue::assertPushed(SyncCustomerToAvaTaxJob::class, 1);
});

it('dispatches SyncCustomerToAvaTaxJob on update', function () {
    Queue::fake();
    $c = Customer::factory()->create();
    app(CustomerService::class)->update($c, ['name' => 'Renamed']);
    Queue::assertPushed(SyncCustomerToAvaTaxJob::class, 1);
});
```

---

## GREEN — Implementation

### `AvaTaxService::upsertCustomer()` signature
```php
public function upsertCustomer(Customer $customer): ?string
```
- Returns AvaTax customer code on success; `null` on failure (disabled, config bad, SDK error).
- Builds payload from `$customer->id`, `name`, `email`, `tax_identification_number`, `entity_use_code`, default address from `$customer->addresses()->where('is_default', true)->first()`.
- Calls `$this->makeClient()->createCustomers($companyId, [$customerModel])`.
- Returns `$response->value[0]->customerCode ?? null`.
- Try/catch + `Log::warning('AvaTax upsertCustomer failed', [...])`. Never throws.

### `SyncCustomerToAvaTaxJob`
```php
final class SyncCustomerToAvaTaxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(public Customer $customer) {}

    public function handle(AvaTaxService $svc): void
    {
        $code = $svc->upsertCustomer($this->customer);
        if ($code !== null) {
            $this->customer->forceFill([
                'avatax_customer_id' => $code,
                'avatax_synced_at' => now(),
            ])->save();
        }
    }
}
```

### `CustomerService` wiring
```php
public function store(array $data): Customer
{
    $customer = Customer::create($data);
    SyncCustomerToAvaTaxJob::dispatch($customer);
    return $customer;
}

public function update(Customer $customer, array $data): Customer
{
    $customer->update($data);
    SyncCustomerToAvaTaxJob::dispatch($customer);
    return $customer;
}
```

`register()` and `updateProfile()` (portal flow) intentionally do NOT dispatch — portal customers self-register before they have tax info; they get synced on the first admin-side update.

---

## REFACTOR
None expected. Job + service stay focused.

---

## Design Notes

| Decision | Rationale |
|----------|-----------|
| Queue (not synchronous) | Customer create/update must never block on AvaTax latency. Retries are free with Laravel queues. |
| `$tries = 3`, backoff `[30, 60, 120]` | Transient AvaTax errors clear within minutes. Three attempts = ~3.5 min total. Beyond that, ops should investigate via `failed_jobs`. |
| Local row written FIRST, then job dispatched | DB success is non-negotiable. AvaTax sync is best-effort. |
| `avatax_customer_id` nullable | Allows local row to exist before AvaTax knows about it. Queries can filter unsynced rows via `whereNull('avatax_customer_id')`. |
| `avatax_synced_at` timestamp | Cheap "last sync" indicator. Reconciliation report (future) compares this to current Customer `updated_at` to find drift. |
| `entity_use_code` as 2-char string | Matches AvaTax's documented codes (A/B/C/D/E/F/G/...). Stored as-is — no enum because AvaTax may add new codes. |
| `register()`/`updateProfile()` do NOT dispatch | Portal self-signup has no tax data. Sync happens later via admin update. |
| Job uses `SerializesModels` | Standard Laravel — fresh customer load inside `handle()`. |

---

## Out of Scope (Future Phases)

- **Sync on default-address change** — currently we only fire on Customer create/update, not when `CustomerAddress` is added/changed. Phase 4.1 if address mismatches start causing tax-rate drift.
- **Reverse sync (AvaTax → local)** — if exemption certs are managed in AvaTax Console, we don't currently pull them back. Manual entry only.
- **Backfill command** — `php artisan avatax:sync-customers` to retry all `whereNull('avatax_customer_id')`. Trivial to add later.
- **UI badge** — "Synced ✓ / Not synced ⚠️" in customer detail. Defer.

---

## Tests

```bash
php artisan test tests/Unit/AvaTaxServiceTest.php tests/Unit/Jobs/SyncCustomerToAvaTaxJobTest.php tests/Unit/CustomerServiceTest.php
```

Expected: all existing tests pass + 6 new ones (2 service, 2 job, 2 customer-service-wiring).
