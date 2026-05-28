# 16 — Audit Log Integration

> **Layer 5 — Presentation.** Depends on `04-models.md`, `07-service.md`, `15-tests.md`.

## Scope

Wires the Orders module into the existing `AuditLogService`. Specifies:

- Mapping `Order::class` in `AuditLogService::$modelMap`
- WHICH `OrderService` methods call `AuditLogService::log()`
- WHAT action string each call passes
- THE CRITICAL RULE: `delete()` logs **before** the order row is removed (so audit row persists after CASCADE)

**Contract-only file** — describes integration points. Implementation lives in `07-service.md` methods + existing `AuditLogService`.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Add `Order::class => 'Order'` to `AuditLogService::$modelMap` | Audit log can render order references with a friendly label |
| Do NOT add `OrderLine` or `OrderLineFee` to the map | Per ex-19 scope — line/fee changes are captured in the parent order's `changes` column |
| Log fires inside the SAME `DB::transaction` as the state change | Atomic — if order operation rolls back, audit log rolls back too (per `14-events-inventory.md` atomic invariant) |
| **`delete()` logs FIRST, before `$order->delete()`** | After CASCADE wipes the order, the audit_logs row is the only remaining history |
| Action strings are short verbs in past tense | Matches existing `audit_logs.action` convention: `created`, `updated`, `deleted`, `payment_recorded`, `completed` |
| `audit_logs.changes` JSON stores before/after diff (for `updated`) or full snapshot (for `created`/`deleted`) | Existing `AuditLogService` behavior |
| Audit logging is fire-and-forget — if `AuditLogService` throws, the order operation still rolls back | Transactional safety; audit failures are visible during testing |
| No new audit_logs schema changes | Existing table is sufficient |

---

## File modifications

```
app/Services/AuditLogService.php        (modified — add Order to $modelMap)
app/Services/OrderService.php           (modified — 5 log() calls)
```

No new files.

---

## `AuditLogService::$modelMap` — add Order

```php
// In app/Services/AuditLogService.php, add to the existing $modelMap array:
private static array $modelMap = [
    User::class           => 'User',
    Customer::class       => 'Customer',
    Department::class     => 'Department',
    Product::class        => 'Product',
    ProductListing::class => 'Product Listing',
    ProductCategory::class=> 'Product Category',
    Supplier::class       => 'Supplier',
    PurchaseOrder::class  => 'Purchase Order',
    GoodsReceipt::class   => 'Goods Receipt',
    Order::class          => 'Order',           // ← NEW
];
```

> Single-line addition to an existing array. No other AuditLogService changes.

---

## Integration points in `OrderService`

Each public method calls `AuditLogService::log()` at a specific point in its transaction:

### `store(array $data, User $createdBy): Order`

| Aspect | Value |
|--------|-------|
| Action string | `'created'` |
| When | LAST step before returning — after all rows + the `order_placed` event are inserted |
| Changes payload | `[]` (creation — no "before" state; snapshot is in `audit_logs.auditable_id` reference) |
| Tests covered | (audit log assertion in `it_creates_order_with_walk_in_source` may verify a row exists) |

### `update(Order $order, array $data): Order`

| Aspect | Value |
|--------|-------|
| Action string | `'updated'` |
| When | LAST step before returning |
| Changes payload | Diff between original order/lines/fees and new state |
| Notes | Order-level diff includes line/fee changes (line count, fee count, grand_total delta) |

### `delete(Order $order): void`

| Aspect | Value |
|--------|-------|
| Action string | `'deleted'` |
| When | **BEFORE `$order->delete()`** — must persist after CASCADE wipe |
| Changes payload | Full snapshot of order at deletion time |
| Tests covered | `it_calls_audit_log_BEFORE_delete` |

### `recordCashPayment(Order $order, array $data, User $createdBy): Payment`

| Aspect | Value |
|--------|-------|
| Action string | `'payment_recorded'` |
| When | After payment row + status updates + event row are inserted |
| Changes payload | `{method: 'cash', amount: ..., status_before: 'pending', status_after: 'processing'}` |

### `complete(Order $order, User $completedBy): Order`

| Aspect | Value |
|--------|-------|
| Action string | `'completed'` |
| When | After serial flip + inventory_movement + status update + event row |
| Changes payload | `{status_before: 'processing', status_after: 'complete', serials_sold: [SN-200]}` |

---

## The `delete()` BEFORE-delete rule (visual)

```
WITHIN ONE DB::transaction:

  Step 1: AuditLogService::log($order, 'deleted')
          ─── audit_logs INSERT successful
                  │
                  ▼
  Step 2: $order->delete()
          ─── orders + order_lines + order_line_fees + order_events + payments
              all CASCADE deleted
                  │
                  ▼
  Step 3: Transaction commits — audit_logs row persists, every other row gone
```

If Step 1 fails → transaction rolls back, nothing is deleted (safe).
If Step 2 fails → transaction rolls back, audit log row also rolls back (consistent).
If Step 1 happens AFTER Step 2 — `$order` is in invalid state, FK violations may occur. **DO NOT reorder.**

---

## ex-19 audit log rows produced

When ex-19's full lifecycle plays out, the `audit_logs` table receives **3 rows** for the order:

| Row | When | Action | auditable_type | auditable_id | user_id |
|-----|------|--------|----------------|--------------|---------|
| 1 | 10:00 — `store()` succeeds | `created` | `Order` | 19 | 1 (Admin John) |
| 2 | 10:05 — `recordCashPayment()` | `payment_recorded` | `Order` | 19 | 1 |
| 3 | 11:00 — `complete()` | `completed` | `Order` | 19 | 1 |

> No `deleted` row in ex-19 (Rachel's order isn't deleted). The `delete` flow is tested separately.

---

## Dependencies

**Depends on:**
- `04-models.md` — `Order` model
- `07-service.md` — defines the 5 OrderService methods that log
- Existing: `AuditLogService::log($model, $action, $changes = [])`
- Existing: `audit_logs` table (no schema changes)

**Depended on by:**
- `15-tests.md` — `it_calls_audit_log_BEFORE_delete` asserts the rule; feature test `admin_can_hard_delete_pending_order` asserts `audit_logs` row exists after order is gone

---

## Validation gates

- [ ] `Order::class` added to `AuditLogService::$modelMap`
- [ ] All 5 `OrderService` public methods call `AuditLogService::log()`
- [ ] `delete()` logs FIRST (before `$order->delete()`)
- [ ] Every log call is inside the same `DB::transaction` as its state change
- [ ] No log call in private helpers — logging is at the public-method boundary
- [ ] Action strings match the convention (`created/updated/deleted/payment_recorded/completed`)
- [ ] No new schema migrations for audit_logs

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | Audit log provides |
|--------|--------------------|
| `00-overview.md` decision: "Audit log: every state-changing OrderService method" | 5 log calls in service |
| `02-permissions.md` `orders.delete` permission + hard delete | `delete()` logs BEFORE wipe — history preserved |
| `07-service.md` 5 public methods | Each has a log call in its transaction |
| `14-events-inventory.md` atomic invariant | Log fires in same DB::transaction |
| `15-tests.md` `it_calls_audit_log_BEFORE_delete` | Rule enforced; CASCADE wipe doesn't take audit_logs row |
| `15-tests.md` `assertDatabaseHas audit_logs: action='deleted', ...` | Audit row persists after hard delete |

No gaps. Audit log integration is minimal but covers every state-changing path.
