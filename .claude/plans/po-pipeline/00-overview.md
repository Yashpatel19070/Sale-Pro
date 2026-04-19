# PO Pipeline Module — Overview

## Purpose
Tracks every physical unit through the warehouse processing pipeline after it arrives
on a Purchase Order. Each unit gets one `po_unit_job` row. Every action taken on
that unit appends one `po_unit_event` row (immutable audit log).

## Core Concept
When a PO is open and a batch of units arrives:
1. Procurement creates a job for each unit against a PO line.
2. The job moves through stages: `receive → visual → serial_assign → tech → qa → shelf`.
3. Each stage is handled by the department with the matching permission.
4. Users see a queue of jobs for their department — filtered by `current_stage`.
5. A unit can be skipped at tech/qa if the PO has `skip_tech=true`/`skip_qa=true`.
6. If a unit fails at any stage, the job is marked `failed` and a Return PO is auto-created.
7. Once a unit reaches `shelf` and passes, it is received into `inventory_serials` with a
   `receive` movement row written by `InventoryMovementService::receive()`.

## Pipeline Stages (Enum)

| Stage | Department | What happens |
|-------|-----------|--------------|
| `receive` | Procurement | Unit physically arrives. Job created. qty_received on PO line incremented. |
| `visual` | Warehouse | Visual inspection — box, accessories, cosmetic condition. |
| `serial_assign` | Warehouse | Serial number read and assigned to the job. |
| `tech` | Tech | Internal hardware/functional test. Skippable via `skip_tech` on PO. |
| `qa` | QA | Final quality assurance sign-off. Skippable via `skip_qa` on PO. |
| `shelf` | Warehouse | Unit placed on shelf — InventorySerial created, receive movement written. |

## Unit Job Status Values

| Status | Meaning |
|--------|---------|
| `pending` | Waiting for the current stage to begin |
| `in_progress` | Someone has taken the unit for the current stage |
| `passed` | Current stage passed — unit moves to next stage |
| `failed` | Unit failed at this stage — triggers Return PO creation |
| `skipped` | Stage was skipped per PO-level skip flag |

Terminal statuses (job is closed): `passed` (at final stage = shelf), `failed`, `skipped` (at final stage).

## Stage Progression Rules

```
receive → visual → serial_assign → tech (or skip) → qa (or skip) → shelf
```

- After `receive` passes: next stage = `visual`
- After `visual` passes: next stage = `serial_assign`
- After `serial_assign` passes: next stage = `tech` (or `qa` if `skip_tech=true` on PO)
- After `tech` passes: next stage = `qa` (or `shelf` if `skip_qa=true` on PO)
- After `qa` passes: next stage = `shelf`
- After `shelf` passes: job is DONE. `InventoryMovementService::receive()` called. `PurchaseOrderService::checkAndClose()` called.

If a stage is skipped (`skip_tech` or `skip_qa`), one `po_unit_event` with `action=skipped`
is written automatically by the pipeline service before advancing to the next stage.

## Queue View (Per Department)

**Procurement does NOT use the pipeline queue.**
Procurement's entry point is the PO show page (`/admin/purchase-orders/{id}`).
They see PO lines with remaining qty and click "Receive Unit" per line.
That calls `PipelineService::createJob()` which immediately advances the unit past `receive`
into `visual`. There are never pending `receive`-stage jobs sitting in the queue.

**Warehouse, Tech, QA use the pipeline queue** (`/admin/pipeline`):
- Warehouse sees: `visual`, `serial_assign`, `shelf`
- Tech sees: `tech`
- QA sees: `qa`

Filtering: `current_stage IN (stages_for_user_role)` AND `status = pending`.

## File Map

| File | Path |
|------|------|
| Migration (jobs) | `database/migrations/xxxx_create_po_unit_jobs_table.php` |
| Migration (events) | `database/migrations/xxxx_create_po_unit_events_table.php` |
| Model (job) | `app/Models/PoUnitJob.php` |
| Model (event) | `app/Models/PoUnitEvent.php` |
| Factory (job) | `database/factories/PoUnitJobFactory.php` |
| Factory (event) | `database/factories/PoUnitEventFactory.php` |
| Enum (stage) | `app/Enums/PipelineStage.php` |
| Enum (unit status) | `app/Enums/UnitJobStatus.php` |
| Enum (event action) | `app/Enums/UnitEventAction.php` |
| Service | `app/Services/PipelineService.php` |
| Requests | `app/Http/Requests/Pipeline/` |
| Policy | `app/Policies/PoUnitJobPolicy.php` |
| Permission Seeder | `database/seeders/PipelinePermissionSeeder.php` |
| Controller | `app/Http/Controllers/PipelineController.php` |
| Feature Test | `tests/Feature/PipelineControllerTest.php` |
| Unit Test | `tests/Unit/Services/PipelineServiceTest.php` |

## Implementation Order
1. Enums (PipelineStage, UnitJobStatus, UnitEventAction)
2. Migrations → `php artisan migrate`
3. Models + Factories
4. Service (PipelineService)
5. FormRequests + Policy
6. Controller
7. Routes
8. Views (queue index, job detail)
9. Seeder
10. Tests

## Dependencies
- `PurchaseOrder` model (po_unit_jobs.purchase_order_id FK)
- `PoLine` model (po_unit_jobs.po_line_id FK)
- `InventorySerial` model (po_unit_jobs.inventory_serial_id FK — nullable until serial_assign)
- `InventoryMovementService::receive()` (called when unit passes shelf stage)
- `PurchaseOrderService::checkAndClose()` (called after every terminal job)
- `PurchaseOrderService::incrementReceived()` (called when unit passes receive stage)
