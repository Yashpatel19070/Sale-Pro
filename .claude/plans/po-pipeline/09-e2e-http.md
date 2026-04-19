# PO Pipeline Module — E2E HTTP Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run the test suite: `php artisan test --testsuite=E2E --filter=Pipeline`
> 2. Collect all failures, errors, and unexpected passes.
> 3. Report results grouped by test ID (e.g. PL-14, PL-51).
> 4. For each failure: quote the exact error message, the line that failed, and what was expected vs actual.
> 5. Do NOT fix anything. Do NOT edit any `.php`, `.ts`, or plan file. Report only.

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/PipelineE2ETest.php
Run command: php artisan test --testsuite=E2E --filter=Pipeline
```

## Seed / beforeEach

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SupplierPermissionSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(PipelinePermissionSeeder::class);

    $this->admin       = User::factory()->create()->assignRole('admin');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');
    $this->tech        = User::factory()->create()->assignRole('tech');
    $this->qa          = User::factory()->create()->assignRole('qa');

    $this->supplier  = Supplier::factory()->create();
    $this->product   = Product::factory()->create();
    $this->location  = InventoryLocation::factory()->create();
    $this->po        = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line      = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id'        => $this->product->id,
        'qty_ordered'       => 3,
    ]);
});
```

---

## Auth & Queue Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PL-01 | Unauthenticated | GET `/admin/pipeline` | Redirect to login |
| PL-02 | `admin` | GET `/admin/pipeline` | 200 — admin sees all pending jobs |
| PL-03 | `warehouse` | GET `/admin/pipeline` | 200 — warehouse sees visual/serial_assign/shelf jobs |
| PL-04 | `tech` | GET `/admin/pipeline` | 200 — tech sees tech-stage jobs |
| PL-05 | `qa` | GET `/admin/pipeline` | 200 — qa sees qa-stage jobs |
| PL-06 | `procurement` | GET `/admin/pipeline` | 200 — queue empty (no receive-stage pending jobs) |

---

## Full Happy Path — No Skip Flags

Steps: receive → visual → serial_assign → tech → qa → shelf

| # | Step | Actor | Action | Expected |
|---|------|-------|--------|----------|
| PL-10 | Create job (receive) | `procurement` | POST `/admin/pipeline/jobs` `{po_line_id}` | 302; job created at visual stage with status=pending; line `qty_received=1`; PO status=partial |
| PL-11 | Claim visual job | `warehouse` | POST `/admin/pipeline/jobs/{job}/start` | 302 → show; status=in_progress; `assigned_to_user_id` = warehouse |
| PL-12 | Pass visual | `warehouse` | POST `/admin/pipeline/jobs/{job}/pass` | 302; stage=serial_assign; status=pending; `assigned_to_user_id=null` |
| PL-13 | Claim serial_assign | `warehouse` | POST `/admin/pipeline/jobs/{job}/start` | status=in_progress |
| PL-14 | Pass serial_assign with serial | `warehouse` | POST pass `{serial_number: 'SN-ABC-001'}` | stage=tech; `pending_serial_number='SN-ABC-001'` on job |
| PL-15 | Claim tech | `tech` | POST start | status=in_progress |
| PL-16 | Pass tech | `tech` | POST pass | stage=qa; status=pending |
| PL-17 | Claim qa | `qa` | POST start | status=in_progress |
| PL-18 | Pass qa | `qa` | POST pass | stage=shelf; status=pending |
| PL-19 | Claim shelf | `warehouse` | POST start | status=in_progress |
| PL-20 | Pass shelf with location | `warehouse` | POST pass `{inventory_location_id}` | status=passed; `inventory_serial_id` set; InventorySerial created with `serial_number='SN-ABC-001'`; InventoryMovement created |
| PL-21 | PO auto-close | After PL-20 (all lines received + all jobs terminal) | — | PO status=closed; `closed_at` set |

---

## Skip Flags — skip_tech

PO has `skip_tech=true`. After serial_assign → expect qa (not tech).

| # | Step | Expected |
|---|------|----------|
| PL-30 | Create job | Receives at visual |
| PL-31 | Pass visual | stage=serial_assign |
| PL-32 | Pass serial_assign | stage=qa (tech skipped); skip event written for tech stage |
| PL-33 | Pass qa | stage=shelf |

---

## Skip Flags — skip_qa

PO has `skip_qa=true`. After tech → expect shelf.

| # | Step | Expected |
|---|------|----------|
| PL-35 | Pass tech | stage=shelf (qa skipped); skip event written for qa stage |

---

## Skip Both Flags

PO has `skip_tech=true` AND `skip_qa=true`. After serial_assign → shelf directly.

| # | Step | Expected |
|---|------|----------|
| PL-38 | Pass serial_assign | stage=shelf (both tech and qa skipped); 2 skip events written |

---

## Fail Flow

| # | Setup | Actor | Action | Expected |
|---|-------|-------|--------|----------|
| PL-40 | Job at tech, status=in_progress, assigned to tech user | `tech` | POST `/admin/pipeline/jobs/{job}/fail` `{notes: 'Broken screen'}` | status=failed; fail event written; return PO auto-created with type=return; return PO linked via `parent_po_id` |
| PL-41 | After PL-40, all other jobs terminal | — | — | Original PO auto-closes (checkAndClose fires) |

---

## Serial Number — Race Condition Guard

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| PL-50 | InventorySerial already exists with `serial_number='DUP-001'` | POST pass at serial_assign with `serial_number='DUP-001'` | 422; error on `serial_number`: unique violation against inventory_serials |
| PL-51 | Another PoUnitJob has `pending_serial_number='DUP-002'` | POST pass at serial_assign with `serial_number='DUP-002'` | 422; error on `serial_number`: unique violation against po_unit_jobs (race guard) |
| PL-52 | Job with `pending_serial_number='DUP-002'` has status=passed (complete) | POST pass with `serial_number='DUP-002'` | 422 — unique rule is on the column regardless of job status |

---

## Authorization Edge Cases

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PL-60 | `qa` trying to claim visual-stage job | POST `/start` on visual job | 403 — qa has no visual permission |
| PL-61 | `tech` trying to pass qa-stage job | POST `/pass` on qa job | 403 |
| PL-62 | `admin` trying to claim tech-stage job | POST `/start` on tech job | 403 — admin has viewAny not stage permissions |
| PL-63 | Different warehouse user trying to pass a job claimed by another | POST `/pass` | 422 DomainException: "Only the assigned worker can pass this job." |
| PL-64 | `warehouse` trying to fail a job not yet claimed | POST `/fail` | 422 DomainException: "Job must be claimed before it can be failed." |

---

## Fail Validation

| # | Payload | Expected |
|---|---------|----------|
| PL-70 | `fail` with no notes | 422; error on `notes` (required on fail) |
| PL-71 | `fail` with `notes=''` (empty string) | 422; error on `notes` |

---

## Notes

- PL-21 auto-close requires ALL po_lines fulfilled AND all PoUnitJobs in terminal state. Setup: line `qty_ordered=1`, 1 unit passes all stages.
- PL-52: the `unique:po_unit_jobs,pending_serial_number` rule has no `ignore` clause — it blocks even against passed jobs. This is intentional: serial numbers are retired permanently.
- Each step in happy path (PL-10 → PL-21) is sequential and stateful — use one `it()` block or chain steps within a single test for the full flow.
