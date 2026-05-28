# Bug & Findings Tracker

> Source: 3-way audit (/code-review + /security-review + /simplify) on AvaTax + Customer + Order changes.
> Last updated: 2026-05-28

Status legend: 🔴 open · 🟡 in progress · ✅ fixed · ⚪ accepted / won't fix

---

## OPEN — High

### H1 — `email_verified_at` is mass-assignable 🔴
- **Severity:** HIGH (security)
- **File:** `app/Models/Customer.php` (`$fillable`)
- **Issue:** `email_verified_at` is on `security.md`'s NEVER-in-`$fillable` list. A request adding `email_verified_at` to `Customer::create($validated)` / `update($validated)` lets a customer self-verify, bypassing `MustVerifyEmail`.
- **Fix:** remove `email_verified_at` from `$fillable`; it's already set server-side via `markEmailAsVerified()` / `forceFill`.
- **Note:** pre-existing, surfaced during this audit.

### H2 — `Customer::find()` should be `findOrFail()` in calculateTax 🔴
- **Severity:** HIGH (correctness)
- **File:** `app/Http/Controllers/OrderController.php:300`
- **Issue:** If the customer is soft-deleted between form-load and tax-calc, `find()` returns null → `$customer?->tax_exempt` silently treats them as non-exempt → AvaTax called with a stale customerCode.
- **Fix:** use `Customer::findOrFail($customerId)`.

### H3 — Permission magic strings, no `Permission` constants class 🔴
- **Severity:** HIGH (convention, REPEAT across project)
- **Files:** `StoreCustomerRequest.php`, `UpdateCustomerRequest.php`, `CustomerPermissionSeeder.php`
- **Issue:** `'customers.manageTaxExemption'` (and all customer permissions) used as raw strings. Project rule wants `Permission::` constants. A typo fails silently (`can()` returns false).
- **Fix:** create a `Permission` constants class; migrate all permission strings. **Project-wide — own session.**

---

## OPEN — Medium

### M1 — Missing `authorize('view', $customer)` in calculateTax (IDOR) 🔴
- **Severity:** MEDIUM (security)
- **File:** `app/Http/Controllers/OrderController.php:300`
- **Issue:** Sibling endpoints (`customerAddresses`, `storeCustomerAddress`) authorize per-customer; `calculateTax` doesn't. Staff with `orders.viewAny` can enumerate customer IDs and learn exemption status from the zero-tax response.
- **Fix:** add `$this->authorize('view', $customer)` after `findOrFail`.

### M2 — Lazy-load `$address->customer` (N+1 rule) 🔴
- **Severity:** MEDIUM (convention/perf)
- **File:** `app/Services/CustomerAddressService.php:40, 58, 70`
- **Issue:** `update`/`setDefault`/`delete` access `$address->customer` with no prior load — violates "with() always, never lazy load." Not in a loop, low impact.
- **Fix:** `$address->loadMissing('customer')` or pass `$customer` in.

### M3 — Plan drift in `07-service.md` 🔴
- **Severity:** MEDIUM (governance)
- **File:** `app/Services/OrderService.php:26` vs `.claude/plans/Orders/07-service.md`
- **Issue:** Locked plan says constructor injects `AuditLogService` and "Service does NOT call AvaTax." Reality: injects `AvaTaxService` + calls `commitInvoice()`.
- **Fix:** amend the plan to reflect that commit-on-payment belongs in the service (decision: keep code, update plan).

### M4 — `CustomerPermissionSeeder` creates an orphaned `staff` role 🔴
- **Severity:** MEDIUM (seeder consistency)
- **File:** `database/seeders/CustomerPermissionSeeder.php:38`
- **Issue:** Creates a `staff` role; `RoleSeeder` defines `super_admin/admin/manager/sales` — no `staff`. Orphaned role with no middleware/tests.
- **Fix:** align with RoleSeeder or remove.

---

## OPEN — Low

### L1 — `UpdatePortalProfileRequest::authorize()` returns `true` 🔴
- **Severity:** LOW (security smell)
- **File:** `app/Http/Requests/.../UpdatePortalProfileRequest.php`
- **Issue:** Safe in practice (route middleware `auth`+`role:customer`, self-scoped, no ID in request), but `security.md` wants a real check in every `authorize()`.
- **Fix:** optional — document the safety, or add an explicit self-scope check.

---

## OPEN — Cleanup

### S1 — Extract `buildLines()` + `buildAddresses()` in AvaTaxService 🔴
- **Severity:** Cleanup (worthwhile)
- **File:** `app/Services/AvaTaxService.php` — `calculateTax` (60-98) vs `commitInvoice` (413-458)
- **Issue:** ~35 lines of address-building + LineItemModel loop duplicated verbatim, 350 lines apart → drift risk.
- **Fix:** two private helpers (`buildLines`, `buildAddresses`). Note: `calculateTax` keeps its own `$validIndexes` tracking.
- **Explicitly NOT extracting** (simplifier rejected as premature abstraction): customer-payload dup, response-shape parsing, try/catch+log pattern — variations make extraction a net negative.

---

## FIXED (this session)

| # | Severity | Issue | File |
|---|----------|-------|------|
| F1 | 🔴 Critical | `upsertCertificate` had no update branch — duplicate cert per job run | AvaTaxService.php ✅ |
| F2 | 🟡 Med | `fresh()` null risk after soft-delete + extra SELECT | SyncCustomerToAvaTaxJob.php ✅ |
| F3 | 🟡 Med | Duplicated default-address lookup | AvaTaxService.php (`defaultAddressFor`) ✅ |
| F4 | 🟡 Med | Entity-use-code map duplicated | AvaTaxService.php (`EXEMPTION_REASON_NAMES`) ✅ |
| F5 | 🟢 Low | Hardcoded `resale-cert` filename | AvaTaxService.php (`exemption-cert`) ✅ |
| F6 | 🔴 Critical | Tax-exempt fields rode generic `customers.update` perm | Store/UpdateCustomerRequest (`customers.manageTaxExemption` gate) ✅ |
| F7 | 🟠 High | `avatax_*` server-set IDs in `$fillable` | Customer.php ✅ |
| F8 | 🟡 Med | `$e->getMessage()` / `response_preview` leaked tax IDs in logs | AvaTaxService.php ✅ |
| F9 | 🟡 Med | Full `ship_to` address logged | AvaTaxService.php (`ship_to_zone`) ✅ |
| F10 | 🟡 Med | `tax_identification_number` / cert number no charset constraint | Store/UpdateCustomerRequest (regex) ✅ |
| F11 | 🟡 Med | `setDefault` dispatched job inside transaction | CustomerAddressService.php (after commit) ✅ |
| F12 | 🟢 Low | Job used pre-L12 trait imports | SyncCustomerToAvaTaxJob.php (single `Queueable`) ✅ |
| F13 | 🟢 Low | Job had no `failed()` handler | SyncCustomerToAvaTaxJob.php ✅ |
| F14 | 🟡 Med | `calculateTax` short-circuited $0 without telling AvaTax | OrderController.php (passes `entityUseCode`) ✅ |

---

## VERIFIED CLEAN / REFUTED

- `avatax_customer_id`/`avatax_certificate_id`/`avatax_synced_at` correctly excluded from `$fillable` (forceFill only) ✓
- `manageTaxExemption` gate placed correctly (prepareForValidation before authorize) — no bypass via portal flows (explicit whitelists) ✓
- Order-side exemption read-only from persisted row — cannot be injected via request ✓
- Job idempotency on retry — DuplicateEntry handled, create→update branch correct ✓
- `customer-addresses` routes use `scopeBindings()` — no cross-customer IDOR ✓
- No raw SQL with user input; all Eloquent/builder with bindings ✓
- No new dead code or unused imports introduced ✓

---

## PRE-EXISTING (out of scope, noted)

- `CustomerAddressServiceTest::store creates an address` asserts first address should be `is_default=false`, but `store()` correctly forces the first address to default — **test bug**, 1-line assertion fix.
