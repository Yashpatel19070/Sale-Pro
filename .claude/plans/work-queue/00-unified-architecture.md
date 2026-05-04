# Unified Work Queue Architecture

## Purpose

Central job orchestration layer for the entire sale-pro system. Any module (PO, Orders, Refunds, Inventory) creates work orders as tasks for human workers. Workers across departments claim, complete, and release jobs through a single unified interface. One table, one queue, all modules.

---

## Core Principle

**Trigger → Queue → Worker → Side Effect**

No module handles human task assignment itself. Every module that needs a human to do something dispatches a queued Laravel Job, which calls `WorkOrderService` to create work orders. Workers see jobs on their board, claim them, complete them. The side effect (serial assignment, stock update, refund processing) happens inside `WorkOrderService::complete()`.

---

## System Layers

```
┌─────────────────────────────────────────────────────────┐
│                    TRIGGER LAYER                        │
│  PO/GRN  │  Orders  │  Refunds  │  Inventory  │  ...   │
│  (any module dispatches a Laravel queued Job)           │
└──────────────────────┬──────────────────────────────────┘
                       ↓ Laravel Queue (database driver)
┌─────────────────────────────────────────────────────────┐
│                  WORK ORDER LAYER                       │
│  work_orders table + WorkOrderService                   │
│  claim / release / complete / cancel                    │
│  TOCTOU-safe: lockForUpdate() inside DB::transaction    │
└──────────┬──────────────────────┬───────────────────────┘
           ↓                      ↓
┌──────────────────┐   ┌──────────────────────────────────┐
│   SIDE EFFECTS   │   │        AUDIT LAYER               │
│  Serial status   │   │  work_order_events (immutable)   │
│  Inventory mvmt  │   │  append-only, no updates ever    │
│  Auto-chain WOs  │   │  one row per state change        │
└──────────────────┘   └──────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│                  REAL-TIME LAYER                        │
│  Laravel Reverb (self-hosted WebSocket)                 │
│  WorkOrderStatusChanged broadcast on every transition   │
│  Workers see jobs pop in live — no page refresh needed  │
└─────────────────────────────────────────────────────────┘
```

---

## Database Design

### `work_orders` — mutable current state

```sql
id                      BIGINT UNSIGNED PK
type                    VARCHAR(30) NOT NULL        -- WorkOrderType enum
status                  VARCHAR(30) NOT NULL        -- WorkOrderStatus enum, default 'queued'
source_type             VARCHAR(100) NULL           -- polymorphic: 'App\Models\GoodsReceiptLine'
source_id               BIGINT UNSIGNED NULL        -- polymorphic: FK to triggering record
product_id              BIGINT UNSIGNED NOT NULL FK → products
inventory_serial_id     BIGINT UNSIGNED NULL FK → inventory_serials   -- NULL until worker scans
priority                TINYINT UNSIGNED NOT NULL DEFAULT 0
claimed_by_user_id      BIGINT UNSIGNED NULL FK → users RESTRICT
claimed_at              TIMESTAMP NULL
completed_by_user_id    BIGINT UNSIGNED NULL FK → users RESTRICT
completed_at            TIMESTAMP NULL
cancelled_by_user_id    BIGINT UNSIGNED NULL FK → users RESTRICT
cancelled_at            TIMESTAMP NULL
notes                   TEXT NULL
created_at              TIMESTAMP NOT NULL
updated_at              TIMESTAMP NOT NULL
```

**No SoftDeletes** — terminal states (`completed`, `cancelled`) are the record.

**Key indexes:**
- `(type, status)` — job board tab filter
- `(status, claimed_by_user_id)` — "my jobs" query
- `(inventory_serial_id)` — "where is device?" lookup
- `(source_type, source_id)` — find WOs for a given source record
- `(product_id, status)` — product-level job status

### `work_order_events` — immutable audit ledger

```sql
id                  BIGINT UNSIGNED PK
work_order_id       BIGINT UNSIGNED NOT NULL FK → work_orders CASCADE
event               VARCHAR(30) NOT NULL            -- WorkOrderEvent enum
actor_user_id       BIGINT UNSIGNED NOT NULL FK → users RESTRICT
serial_id_snapshot  BIGINT UNSIGNED NULL            -- denormalized at event time
status_before       VARCHAR(30) NULL
status_after        VARCHAR(30) NOT NULL
notes               TEXT NULL
created_at          TIMESTAMP NOT NULL              -- NO updated_at, immutable
```

**No SoftDeletes, no `updated_at`.** Each state change = new row. Pattern identical to `inventory_movements`.

**Partition by month** for long-term health (drop old partitions instantly).

### Scalability

MySQL single node handles this indefinitely for a single-warehouse operation:
- `work_orders`: ~1k–5k rows/day → 100M rows = 55+ years
- `work_order_events`: ~5–10× work orders → still fine at 500M+ rows
- All queries filter on indexed columns — no full table scans
- Archive terminal WOs older than 6 months to `work_orders_archive` to keep hot table small

Sharding only needed for multi-company SaaS at massive scale. Not applicable here.

---

## Enums

### `WorkOrderType` — grows with each new module

| Value | Trigger module | Queue for | Side effect on complete |
|-------|---------------|-----------|------------------------|
| `receive` | PO / GRN | Warehouse | Create `InventorySerial` at `pending_qc`, auto-create `quality_check` WO |
| `quality_check` | Auto (after receive) | QC | Set `serial.status = in_stock`, record `InventoryMovement(receive)` |
| `fulfill` | Orders (future) | Sales/Fulfillment | Set `serial.status = sold`, record `InventoryMovement(sale)` |
| `return_inspection` | Refunds (future) | QC | Inspect returned unit, decide restock or write-off |
| `stock_count` | Inventory (future) | Warehouse | Verify serial location matches system record |
| `damaged_processing` | Inventory (future) | QC | Assess damage, update serial status |

**Adding a new type**: add enum case + branch in `WorkOrderService::complete()`. Zero table changes.

### `WorkOrderStatus`

```
queued → claimed → completed
                 ↘ cancelled (any non-terminal state)
```

| Status | Meaning |
|--------|---------|
| `queued` | Available for any eligible worker to claim |
| `claimed` | Locked to one worker — others see "Claimed by [name]" |
| `completed` | Done. Side effects applied. Terminal. |
| `cancelled` | Abandoned by admin/manager. Terminal. |

### `SerialStatus` (extended for QC gate)

| Status | On shelf? | Set by |
|--------|-----------|--------|
| `pending_qc` | No | `receive` WO complete |
| `in_stock` | Yes | `quality_check` WO complete |
| `sold` | No | `fulfill` WO complete |
| `damaged` | No | Manual or `damaged_processing` WO complete |
| `missing` | No | Manual or `stock_count` WO complete |

---

## Extensibility Pattern

### Adding a new trigger (zero schema changes)

1. Create `app/Jobs/CreateWorkOrdersFrom{Module}.php`
2. Dispatch it from `{Module}Service::store()` **after** the transaction commits
3. Job calls `WorkOrderService::create(['type' => WorkOrderType::NewType, 'source_type' => NewModel::class, 'source_id' => $record->id, ...])`

```php
// Example: Order system (future)
// In OrderService::store():
$order = DB::transaction(fn() => /* create order + lines */);
dispatch(new CreateWorkOrdersFromOrder($order));  // after commit
return $order;

// In CreateWorkOrdersFromOrder::handle():
foreach ($order->lines as $line) {
    $service->create([
        'type'        => WorkOrderType::Fulfill,
        'source_type' => OrderLine::class,
        'source_id'   => $line->id,
        'product_id'  => $line->product_id,
        'priority'    => $order->is_express ? 10 : 0,
    ]);
}
```

### Adding a new work order type (minimal changes)

1. Add case to `app/Enums/WorkOrderType.php`
2. Add `requiredPermission()` match arm
3. Add branch in `WorkOrderService::complete()` for side effects
4. Add permission constant in `Permission.php`
5. Update seeder to assign permission to relevant role
6. Add tab to job board index view

### Module integration map (planned)

| Module | Status | Trigger Job | WO Types Created |
|--------|--------|-------------|-----------------|
| GRN (PO module) | Planned | `CreateWorkOrdersFromGrn` | `receive` |
| Auto-chain | Built-in | None (service internal) | `quality_check` after `receive` |
| Orders | Future | `CreateWorkOrdersFromOrder` | `fulfill` |
| Refunds | Future | `CreateWorkOrdersFromRefund` | `return_inspection` |
| Inventory stocktake | Future | `CreateWorkOrdersFromStocktake` | `stock_count` |

---

## Tech Stack

| Layer | Technology | Reason |
|-------|-----------|--------|
| Backend | PHP 8.2 + Laravel 12 | Existing stack |
| Database | MySQL / MariaDB | Existing; handles millions of rows with indexes |
| Queue | Laravel Queue (database driver) | Existing; no Redis needed for this scale |
| WebSocket | Laravel Reverb (self-hosted) | Free, no external service, Pusher-compatible protocol |
| Frontend | Blade + Alpine.js + Tailwind v3 | Existing stack; Echo.js for WebSocket listener |
| Auth / Permissions | Spatie Laravel Permission | Existing; per-type queue access via permissions |
| Testing | Pest | Existing |

---

## Role & Permission Matrix

| Permission | super-admin | admin | manager | sales | warehouse | qc |
|-----------|:-----------:|:-----:|:-------:|:-----:|:---------:|:--:|
| view-any | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| claim | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ |
| release | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ |
| complete | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ |
| cancel | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| work-receive | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| work-qc | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| work-fulfill | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |

Managers oversee without doing — they cancel problem jobs but don't claim them.

---

## "Where Is the Device?" Query

At any point, given a serial number:

```php
// 1. Current physical state
$serial = InventorySerial::with('location', 'product')->find($serialId);
// → status (pending_qc / in_stock / sold), location name

// 2. Who has it right now (if being worked on)
$activeWo = WorkOrderService::activeForSerial($serialId);
// → claimed_by name, type (receive/qc/fulfill), claimed_at

// 3. Full movement history
$movements = InventoryMovement::forSerial($serialId)->with('user', 'fromLocation', 'toLocation')->get();
// → when it moved, who moved it, from/to

// Combined → renders "Active Work Order" card on inventory-serials/show.blade.php
```

---

## Critical Rules (NEVER break)

1. **Dispatch trigger jobs AFTER the transaction commits** — inside a transaction risks the job running before DB commits
2. **TOCTOU-safe claiming** — always `lockForUpdate()` inside `DB::transaction()` in `WorkOrderService::claim()`
3. **`receive` complete creates serial at `pending_qc`** — never `in_stock` directly; QC gate must run first
4. **`quality_check` complete is where `InventoryMovement(receive)` gets recorded** — not at `receive` complete
5. **No `LogsActivity` on `WorkOrder` or `WorkOrderEvent`** — `work_order_events` IS the audit; double-logging wastes storage and adds confusion
6. **Terminal WOs never mutate** — `completed` and `cancelled` are read-only after transition
7. **`work_order_events` has no `updated_at`** — `const UPDATED_AT = null` in the model; rows are immutable

---

## Implementation Reference

Initial implementation: `.claude/plans/harmonic-wobbling-quail.md`
Built by remote Ultraplan session: https://claude.ai/code/session_01YGEBndnchvjsiR63d27o9R
Result: 34 new tests, 735 total passing, 0 regressions (2026-04-26)
