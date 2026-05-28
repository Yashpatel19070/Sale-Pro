# AvaTax — Phase 5: Exemption Certificates (End-to-End)

## Goal
Issue (and update) exemption certificates in AvaTax for tax-exempt customers, so transactions to them actually return $0 tax in AvaTax's response. Admin enters cert details in the Customer form; on save, the existing `SyncCustomerToAvaTaxJob` syncs the customer **and** the cert.

## Why this matters
Phase 4 registers the customer in AvaTax with their tax ID. But AvaTax does NOT apply exemption based on `tax_exempt=true` alone — it requires a valid certificate on file for the customer + jurisdiction. Without this phase, exempt customers still get taxed.

## Files
| File | Action |
|------|--------|
| `database/migrations/2026_05_27_230000_add_avatax_certificate_fields_to_customers_table.php` | NEW — 4 cols |
| `app/Models/Customer.php` | add 4 fillable + 2 date casts |
| `app/Http/Requests/StoreCustomerRequest.php` | add validation for 6 exemption fields |
| `app/Http/Requests/UpdateCustomerRequest.php` | same |
| `app/Services/AvaTaxService.php` | add `upsertCertificate()` |
| `app/Jobs/SyncCustomerToAvaTaxJob.php` | after upsertCustomer success, call upsertCertificate |
| `resources/views/customers/create.blade.php` | add Tax & Exemption section |
| `resources/views/customers/edit.blade.php` | same |
| `resources/views/customers/show.blade.php` | display synced AvaTax IDs + cert state |
| `tests/Unit/AvaTaxServiceTest.php` | 4 new cert tests |
| `tests/Unit/Jobs/SyncCustomerToAvaTaxJobTest.php` | 2 new cert tests |
| `tests/Feature/CustomerControllerTest.php` | 2 new form-validation tests |

---

## Schema

```php
Schema::table('customers', function (Blueprint $t) {
    $t->date('exemption_signed_date')->nullable()->after('exemption_certificate_number');
    $t->date('exemption_expires_at')->nullable()->after('exemption_signed_date');
    $t->string('exemption_exposure_zone', 60)->nullable()->after('exemption_expires_at');
    $t->unsignedBigInteger('avatax_certificate_id')->nullable()->after('avatax_synced_at');
});
```

| Column | Purpose |
|--------|---------|
| `exemption_signed_date` | when cert was signed by customer |
| `exemption_expires_at` | when cert expires |
| `exemption_exposure_zone` | state/jurisdiction (e.g. "California") |
| `avatax_certificate_id` | numeric ID returned by AvaTax `createCertificates` |

---

## FormRequest validation (Store + Update — identical rules block)

```php
'tax_exempt' => ['nullable', 'boolean'],
'tax_identification_number' => ['nullable', 'string', 'max:64'],
'entity_use_code' => ['nullable', 'string', 'regex:/^[A-Z]$/', Rule::in(self::ENTITY_USE_CODES)],
'exemption_certificate_number' => ['nullable', 'string', 'max:64', 'required_if:tax_exempt,true'],
'exemption_signed_date' => ['nullable', 'date', 'required_if:tax_exempt,true', 'before_or_equal:today'],
'exemption_expires_at' => ['nullable', 'date', 'required_if:tax_exempt,true', 'after:exemption_signed_date'],
'exemption_exposure_zone' => ['nullable', 'string', 'max:60', 'required_if:tax_exempt,true'],
```

Plus a constant on each request:
```php
private const ENTITY_USE_CODES = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
```

### Entity use codes (UI labels)
| Code | Label |
|------|-------|
| A | Federal Government |
| B | State/Local Government |
| C | Tribal Government |
| D | Foreign Diplomat |
| E | Charitable / Religious |
| F | Religious |
| G | Resale |
| H | Agriculture |
| I | Industrial Production / Manufacturer |
| J | Direct Pay Permit |
| K | Direct Mail |
| L | Other |
| M | Educational |
| N | Local Government |

---

## RED — Tests first

### `AvaTaxServiceTest.php` — append

```php
it('upsertCertificate_creates_certificate_when_avatax_customer_id_set', function () {
    // RED: customer has avatax_customer_id + cert fields, no avatax_certificate_id
    // Assert: createCertificates called once with correct payload (signedDate,
    //   expirationDate, exemptionNumber, exemptionReason='G', exposureZone='California',
    //   customers[0].customerCode === customer->id)
    // Assert: returns the AvaTax-assigned certificate id (int)
});

it('upsertCertificate_calls_updateCertificate_when_avatax_certificate_id_set', function () {
    // RED: customer has avatax_customer_id + avatax_certificate_id + cert fields
    // Assert: updateCertificate called once with companyId, cert_id, payload
    // Assert: createCertificates NOT called
});

it('upsertCertificate_returns_null_when_customer_not_synced', function () {
    // avatax_customer_id is null → can't attach cert → return null + log
});

it('upsertCertificate_returns_null_when_cert_data_incomplete', function () {
    // signed_date or expires_at or exposure_zone missing → skip (no log warning, just no-op)
});
```

### `SyncCustomerToAvaTaxJobTest.php` — append

```php
it('also calls upsertCertificate when customer has cert data', function () {
    // upsertCustomer returns code → upsertCertificate also called → avatax_certificate_id saved
});

it('does not call upsertCertificate when customer has no cert data', function () {
    // upsertCustomer returns code → no cert fields → upsertCertificate NOT called
});
```

### `CustomerControllerTest.php` — append

```php
it('store accepts tax_exempt with cert fields', function () {
    // POST with all fields → 302 redirect, customer saved with cert fields populated
});

it('store rejects expires_at before signed_date', function () {
    // POST with expires_at < signed_date → 422 validation error on expires_at
});
```

---

## GREEN — Implementation

### `AvaTaxService::upsertCertificate(Customer $c): ?int`

Logic:
1. If `! $this->isEnabled()` → return null
2. If `empty($c->avatax_customer_id)` → log warning, return null (customer must exist in AvaTax first)
3. If any of `exemption_signed_date`, `exemption_expires_at`, `exemption_exposure_zone` is empty → return null silently (just no cert data, not an error)
4. Build payload (stdClass):
   ```php
   $cert = new \stdClass;
   $cert->signedDate = $c->exemption_signed_date->toDateString();
   $cert->expirationDate = $c->exemption_expires_at->toDateString();
   $cert->exemptionNumber = $c->exemption_certificate_number;
   $cert->exemptionReason = (object) ['code' => $c->entity_use_code ?? 'L'];
   $cert->exposureZone = (object) ['name' => $c->exemption_exposure_zone];
   $cert->customers = [(object) ['customerCode' => (string) $c->id]];
   ```
5. If `! empty($c->avatax_certificate_id)` → `updateCertificate($companyId, $c->avatax_certificate_id, $cert)`
   Else → `createCertificates($companyId, null, [$cert])`
6. Parse response (same array/object/value shape handling as `upsertCustomer`)
7. Return AvaTax cert id (int) on success, null on failure
8. Logging:
   - `Log::info('AvaTax certificate created', [...])`
   - `Log::info('AvaTax certificate updated', [...])`
   - `Log::warning('AvaTax upsertCertificate skipped: customer not synced')`
   - `Log::warning('AvaTax upsertCertificate received unexpected response shape')`
   - `Log::warning('AvaTax upsertCertificate failed')` (catch block, includes operation)

### `SyncCustomerToAvaTaxJob` wiring

```php
public function handle(AvaTaxService $svc): void
{
    $code = $svc->upsertCustomer($this->customer);
    if ($code === null) return;

    $this->customer->forceFill([
        'avatax_customer_id' => $code,
        'avatax_synced_at' => now(),
    ])->save();

    // Cert sync — only fires if customer has cert data
    $certId = $svc->upsertCertificate($this->customer->fresh());
    if ($certId !== null) {
        $this->customer->forceFill(['avatax_certificate_id' => $certId])->save();
    }
}
```

---

## UI

### `customers/create.blade.php` + `customers/edit.blade.php`

Add an Alpine-driven section after the existing fields:

```blade
<div x-data="{ taxExempt: @json(old('tax_exempt', $customer->tax_exempt ?? false)) }" class="mt-6 border-t pt-4">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Tax & Exemption</h3>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs">Federal Tax ID / Taxpayer ID</label>
            <input type="text" name="tax_identification_number"
                value="{{ old('tax_identification_number', $customer->tax_identification_number ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
        </div>

        <div class="flex items-center mt-6">
            <input type="hidden" name="tax_exempt" value="0">
            <input type="checkbox" name="tax_exempt" value="1" x-model="taxExempt"
                class="mr-2 rounded border-gray-300" />
            <label class="text-sm font-medium">Tax-Exempt Customer</label>
        </div>
    </div>

    <div x-show="taxExempt" x-transition class="mt-4 grid grid-cols-2 gap-4 bg-amber-50 p-3 rounded">
        <div>
            <label class="block text-xs">Exemption Reason *</label>
            <select name="entity_use_code"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">— select —</option>
                <option value="G" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'G')>G — Resale</option>
                <option value="E" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'E')>E — Charitable</option>
                <option value="A" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'A')>A — Federal Government</option>
                <option value="B" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'B')>B — State/Local Government</option>
                <option value="F" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'F')>F — Religious</option>
                <option value="H" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'H')>H — Agriculture</option>
                <option value="I" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'I')>I — Industrial / Manufacturer</option>
                <option value="J" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'J')>J — Direct Pay Permit</option>
                <option value="K" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'K')>K — Direct Mail</option>
                <option value="M" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'M')>M — Educational</option>
                <option value="N" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'N')>N — Local Government</option>
                <option value="C" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'C')>C — Tribal Government</option>
                <option value="D" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'D')>D — Foreign Diplomat</option>
                <option value="L" @selected(old('entity_use_code', $customer->entity_use_code ?? '') === 'L')>L — Other</option>
            </select>
        </div>

        <div>
            <label class="block text-xs">Certificate Number *</label>
            <input type="text" name="exemption_certificate_number"
                value="{{ old('exemption_certificate_number', $customer->exemption_certificate_number ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
        </div>

        <div>
            <label class="block text-xs">Exposure Zone (state) *</label>
            <input type="text" name="exemption_exposure_zone" placeholder="California"
                value="{{ old('exemption_exposure_zone', $customer->exemption_exposure_zone ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
        </div>

        <div>
            <label class="block text-xs">Signed Date *</label>
            <input type="date" name="exemption_signed_date"
                value="{{ old('exemption_signed_date', optional($customer->exemption_signed_date ?? null)->toDateString()) }}"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
        </div>

        <div>
            <label class="block text-xs">Expires At *</label>
            <input type="date" name="exemption_expires_at"
                value="{{ old('exemption_expires_at', optional($customer->exemption_expires_at ?? null)->toDateString()) }}"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
        </div>
    </div>
</div>
```

### `customers/show.blade.php`

Display-only section:
```
AvaTax Customer ID: 22
AvaTax Certificate ID: 1456
Last Synced: 2026-05-27 22:53:24
Tax Exempt: yes (G — Resale, expires 2027-01-15)
```

---

## REFACTOR
None expected. `upsertCertificate` mirrors `upsertCustomer` shape — same branching, same logging style. Duplication is acceptable for clarity.

---

## Design Notes

| Decision | Rationale |
|----------|-----------|
| Cert sync rides in the existing `SyncCustomerToAvaTaxJob` | One job per customer save — keeps things simple. Customer create must succeed before cert upload (FK to customerCode in AvaTax). |
| `upsertCertificate` returns null silently when cert data incomplete | This is normal state — not every customer is exempt. No log spam. |
| `entity_use_code` enum constrained at FormRequest level (`Rule::in([...])`) | Catches typos. AvaTax may add new codes later → update the constant. |
| `required_if:tax_exempt,true` on cert fields | Admin can mark tax_exempt without all cert data only if they're saving an in-progress record — but then sync won't push cert. With required_if, we force completion. |
| `before_or_equal:today` on signed_date | Cert can't be signed in the future. |
| `after:exemption_signed_date` on expires_at | Expiry must be later than signing. |
| `exposure_zone` free-text vs select | Free text for v1 — AvaTax accepts state names ("California") or zone codes. UI placeholder shows the format. Tighten to a select in v2 if needed. |
| Cert ID stored as `unsignedBigInteger` | AvaTax cert IDs are numeric (different from customer codes which we send as strings). |

---

## Live Verification (after GREEN)

1. Open `/admin/customers/22/edit` for GIO'S SMOG AUTO (or whichever real test customer)
2. Tick "Tax-Exempt Customer"
3. Reason: G — Resale
4. Certificate #: `218646848-RESALE`
5. Exposure Zone: `California`
6. Signed: `2026-01-15`
7. Expires: `2027-01-15`
8. Save → verify queue worker processes the job
9. Check `avatax_certificate_id` populates on customer row
10. Log into AvaTax sandbox → Customers → customer 22 → Certificates tab → confirm cert appears
11. (Optional) Run a `calculateTax` call with that customer's address → confirm tax = $0

---

## Out of Scope (v2 follow-ups)

- **PDF upload** — AvaTax `uploadCertificateImage` API; needs file storage + base64 encoding
- **Multiple certs per customer** — different states with separate certs; v1 supports one cert per customer
- **Cert expiry alerts** — nightly job that flags customers whose cert expires within 30 days
- **Reverse sync** — if cert is updated in AvaTax Console, pull changes back to our DB

---

## Tests

```bash
php artisan test tests/Unit/AvaTaxServiceTest.php tests/Unit/Jobs/SyncCustomerToAvaTaxJobTest.php tests/Feature/CustomerControllerTest.php
```

Expected: all existing tests pass + 8 new ones (4 service, 2 job, 2 controller).
