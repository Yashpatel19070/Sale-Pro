# Purchase Order Module — E2E HTTP Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run the test suite: `php artisan test --testsuite=E2E --filter=PurchaseOrder`
> 2. Collect all failures, errors, and unexpected passes.
> 3. Report results grouped by test ID (e.g. PO-30, PO-44).
> 4. For each failure: quote the exact error message, the line that failed, and what was expected vs actual.
> 5. Do NOT fix anything. Do NOT edit any `.php`, `.ts`, or plan file. Report only.

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/PurchaseOrderE2ETest.php
Run command: php artisan test --testsuite=E2E --filter=PurchaseOrder
```

## Seed / beforeEach

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SupplierPermissionSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(PipelinePermissionSeeder::class);

    $this->superAdmin  = User::factory()->create()->assignRole('super-admin');
    $this->admin       = User::factory()->create()->assignRole('admin');
    $this->manager     = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create(['is_active' => true]);
    $this->product  = Product::factory()->create();
    $this->location = InventoryLocation::factory()->create();
});
```

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PO-01 | Unauthenticated | GET `/admin/purchase-orders` | Redirect to login |
| PO-02 | `admin` | GET `/admin/purchase-orders` | 200 OK |
| PO-03 | `procurement` | GET `/admin/purchase-orders` | 200 OK |
| PO-04 | `warehouse` | GET `/admin/purchase-orders` | 403 |

---

## Lifecycle — Happy Path (create → confirm → partial → close)

| # | Step | Setup | Expected |
|---|------|-------|----------|
| PO-10 | Create draft | POST `/admin/purchase-orders` with valid supplier + 2 lines | 302 → show; status=draft; po_number format `PO-YYYY-XXXX`; 2 po_lines created |
| PO-11 | Lines have snapshots | After PO-10 | Both po_lines have `snapshot_stock` and `snapshot_inbound` recorded (≥ 0) |
| PO-12 | Confirm draft | POST `/admin/purchase-orders/{id}/confirm` | 302 → show; status=open; `confirmed_at` set |
| PO-13 | Edit blocked after confirm | PATCH `/admin/purchase-orders/{id}` | 403 — policy blocks edit on non-draft |
| PO-14 | Receive unit (pipeline) | Create PoUnitJob for line | PO status → partial; line `qty_received` +1 |
| PO-15 | Receive all units + pass shelf | All units passed to shelf stage | PO status → closed; `closed_at` set |

---

## Create Validation Edge Cases

| # | Payload | Expected |
|---|---------|----------|
| PO-20 | Missing `supplier_id` | 422; error on `supplier_id` |
| PO-21 | `supplier_id` does not exist | 422; error on `supplier_id` |
| PO-22 | `lines` empty array | 422; error on `lines` (min:1) |
| PO-23 | `lines.0.qty_ordered = 0` | 422; error on `lines.0.qty_ordered` (min:1) |
| PO-24 | `lines.0.qty_ordered = 10001` | 422; error on `lines.0.qty_ordered` (max:10000) |
| PO-25 | `lines.0.unit_price = 0` | 422; error on `lines.0.unit_price` (min:0.01) |
| PO-26 | `lines.0.product_id` does not exist | 422; error on `lines.0.product_id` |
| PO-27 | Valid data, `warehouse` role | 403 |

---

## Cancel Edge Cases

| # | Setup | Payload | Expected |
|---|-------|---------|----------|
| PO-30 | Draft PO | `cancel_notes = 'Valid reason here'` | 302 → show; status=cancelled; `cancel_notes` persisted; `cancelled_at` set |
| PO-31 | Draft PO | `cancel_notes = 'short'` (< 10 chars) | 422; error on `cancel_notes` (min:10) |
| PO-32 | Draft PO | `cancel_notes` missing | 422; error on `cancel_notes` (required) |
| PO-33 | Open PO, no units received | Valid notes | 302 → cancelled |
| PO-34 | Open PO, 1 unit received | Valid notes | 422 (DomainException): "Cannot cancel a PO that has received units." |
| PO-35 | Partial PO | Valid notes | 422 (DomainException): "Only draft or open POs can be cancelled." |
| PO-36 | Closed PO | Valid notes | 422 (DomainException): "Only draft or open POs can be cancelled." |
| PO-37 | Open PO | Valid notes, `procurement` role | 403 |

---

## Reopen Edge Cases

| # | Setup | Actor | Expected |
|---|-------|-------|----------|
| PO-40 | Closed PO (`reopen_count=0`) | `manager` | 302 → show; status=open; `reopen_count=1`; `reopened_at` set; `closed_at` NOT nulled |
| PO-41 | Closed PO (`reopen_count=1`) | `manager` | 302 → show; `reopen_count=2` |
| PO-42 | Closed PO (`reopen_count=2`) | `manager` | 422 (DomainException): "Third or subsequent reopens require Super Admin approval." |
| PO-43 | Closed PO (`reopen_count=2`) | `super-admin` | 302 → show; `reopen_count=3` |
| PO-44 | Closed PO; unit job at shelf with `status=passed` | `manager` | 422: "Cannot reopen: one or more units from this PO are currently on the shelf." |
| PO-45 | Closed PO; unit job at shelf with `status=failed` | `manager` | 302 → success — failed shelf job does NOT block reopen |
| PO-46 | Open PO | `manager` | 422: "Only closed POs can be reopened." |
| PO-47 | Closed PO | `procurement` | 403 |

---

## PO Number Generation

| # | Setup | Expected |
|---|-------|----------|
| PO-50 | No POs exist this year | First PO gets `PO-{YEAR}-0001` |
| PO-51 | 2 POs already created this year | Third PO gets `PO-{YEAR}-0003` |
| PO-52 | 1 PO from previous year (created via `created_at` override) | New PO in current year resets to `PO-{YEAR}-0001` |

---

## Search / Filter

| # | Setup | Filter | Expected rows |
|---|-------|--------|---------------|
| PO-60 | 3 POs: `PO-2026-0001`, `PO-2026-0002`, `PO-2026-0003` | `search=0002` | 1 row |
| PO-61 | PO with supplier "Acme Corp" | `search=acme` | PO appears (supplier name match) |
| PO-62 | Mix of draft + open POs | `status=draft` | Only draft POs returned |
| PO-63 | Mix of supplier A + supplier B | `supplier_id={A.id}` | Only supplier A's POs |
| PO-64 | Mix of purchase + return type | `type=return` | Only return POs |

---

## Notes

- PO-45 verifies the reopen shelf guard uses `UnitJobStatus::Passed` (not any terminal state).
- PO-44 / PO-45 together validate the enum fix to `reopen()` — hardcoded `'passed'` string would pass both, but enum comparison is type-safe.
- PO-52 requires manipulating `created_at` on the factory: `PurchaseOrder::factory()->create(['created_at' => now()->subYear()])`.
