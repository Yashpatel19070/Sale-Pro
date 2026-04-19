# PO Return Module — E2E HTTP Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run the test suite: `php artisan test --testsuite=E2E --filter=PoReturn`
> 2. Collect all failures, errors, and unexpected passes.
> 3. Report results grouped by test ID (e.g. RT-32, RT-43).
> 4. For each failure: quote the exact error message, the line that failed, and what was expected vs actual.
> 5. Do NOT fix anything. Do NOT edit any `.php`, `.ts`, or plan file. Report only.

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/PoReturnE2ETest.php
Run command: php artisan test --testsuite=E2E --filter=PoReturn
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
    $this->manager     = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');

    $this->supplier   = Supplier::factory()->create();
    $this->product    = Product::factory()->create();
    $this->originalPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line       = PoLine::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'product_id'        => $this->product->id,
        'unit_price'        => '350.00',
    ]);
    $this->job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'po_line_id'        => $this->line->id,
    ]);

    // Pre-built return PO (auto-created in real flow via PipelineService::fail)
    $this->returnPo = PurchaseOrder::factory()->returnType()->create([
        'parent_po_id'       => $this->originalPo->id,
        'supplier_id'        => $this->supplier->id,
        'status'             => 'open',
        'confirmed_at'       => now(),
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create([
        'purchase_order_id' => $this->returnPo->id,
        'product_id'        => $this->product->id,
        'qty_ordered'       => 1,
        'unit_price'        => '350.00',
    ]);
});
```

---

## Auth & Access — Index

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| RT-01 | Unauthenticated | GET `/admin/po-returns` | Redirect to login |
| RT-02 | `admin` | GET `/admin/po-returns` | 200 |
| RT-03 | `procurement` | GET `/admin/po-returns` | 200 |
| RT-04 | `warehouse` | GET `/admin/po-returns` | 403 |

---

## Index — Only Return Type POs Shown

| # | Setup | Expected |
|---|-------|----------|
| RT-10 | 2 purchase-type POs + 1 return-type PO in DB | Index shows only the 1 return PO |
| RT-11 | Search by return PO number | Only matching return PO shown |
| RT-12 | Filter by status=open | Only open return POs shown |
| RT-13 | No return POs exist | Empty state message |

---

## Show — Return PO Detail

| # | Setup | Actor | Expected |
|---|-------|-------|----------|
| RT-20 | Return PO exists | `admin` | GET `/admin/po-returns/{returnPo}` → 200; parent PO link visible; supplier name visible |
| RT-21 | Purchase-type PO ID used | `admin` | GET `/admin/po-returns/{purchasePo}` → 404 — type guard enforced in controller |
| RT-22 | `warehouse` | Return PO | 403 |

---

## Full Auto-Creation Flow (Integration)

This test drives `PipelineService::fail()` and asserts the return PO is created correctly.

| # | Step | Action | Expected |
|---|------|--------|----------|
| RT-30 | Set up in-progress job | Factory: job at visual, status=in_progress, assigned to warehouse | — |
| RT-31 | Fail the job | POST `/admin/pipeline/jobs/{job}/fail` `{notes: 'Cracked screen on arrival'}` | 302; job status=failed |
| RT-32 | Return PO auto-created | Check DB | One return PO exists: type=return; parent_po_id=originalPo.id; status=open; supplier_id matches; 1 line with same product_id and unit_price |
| RT-33 | Return PO line qty | Check return PO line | qty_ordered=1 (one failed unit = one return line) |
| RT-34 | Return PO notes | Check return PO | Notes contain the job id and stage name |
| RT-35 | Multiple failures same PO | Fail 2 jobs on same original PO | 2 separate return POs created (one per failed unit) |

---

## Close Return PO

| # | Setup | Actor | Action | Expected |
|---|-------|-------|--------|----------|
| RT-40 | Open return PO | `manager` | POST `/admin/po-returns/{returnPo}/close` | 302 → show; status=closed; `closed_at` set |
| RT-41 | Open return PO | `procurement` | POST close | 403 — only manager+ can close return POs |
| RT-42 | Already-closed return PO | `manager` | POST close | 422 DomainException: "Return PO is already closed." |
| RT-43 | Purchase-type PO used as ID | `manager` | POST `/admin/po-returns/{purchasePo}/close` | 404 — type guard |

---

## Close Redirect & Message

| # | Expected |
|---|----------|
| RT-50 | After close → redirected to `/admin/po-returns/{id}` |
| RT-51 | Flash message: "Return PO {po_number} closed." |
| RT-52 | Show page after close: status badge = "Closed"; `closed_at` timestamp visible |

---

## Notes

- RT-21 / RT-43: the controller checks `$po->type === PoType::Return` before proceeding — aborts with 404 if not a return PO. This prevents procurement staff from accidentally closing purchase POs via the return route.
- RT-35: Each `PipelineService::fail()` call creates one return PO. Grouping logic (one return PO per original PO) is NOT implemented — by design.
- RT-31 through RT-34 constitute the integration test that bridges pipeline → return modules. The job must be in_progress (claimed) before fail() can be called.
