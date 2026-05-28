# 07 — Service (OrderService)

> **Layer 4 — Behavior.** Depends on `01-enums.md`, `03-schema.md`, `04-models.md`, `06-policy.md`, `14-events-inventory.md`, `15-tests.md`.

## Scope

`OrderService` — single class that owns all order lifecycle logic.

**5 public methods** (each maps to a controller action):
- `store(array $data, User $createdBy): Order`
- `update(Order $order, array $data): Order`
- `delete(Order $order): void`
- `recordCashPayment(Order $order, array $data, User $createdBy): Payment`
- `complete(Order $order, User $completedBy): Order`

**4 private helpers**:
- `generateNumber(): string`
- `recalculateTotals(Order $order): void`
- `allocateSerial(ProductListing $listing): int` — locks a serial via `lockForUpdate`, called by `recordCashPayment`
- `resolveBillingSnapshot(?int $addressId, PaymentMethod $method): array`
- `resolveShippingSnapshot(?int $addressId): array`

> Inventory movement + serial-status flip are inlined inside `recordCashPayment` calling `InventoryMovementService::recordSale()` directly — not extracted into a helper. The plan originally listed `recordSaleMovements` as a helper before the design collapsed payment/sale per issue #6.

**Contract-only file** — no method bodies. Tests in `15-tests.md` drive implementation.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Every public method wraps work in `DB::transaction(fn() => ...)` | Atomic invariant per `14-events-inventory.md` |
| Service does NOT call AvaTax — request layer pre-fills `tax_amount` | AvaTax integration in controller helper (per `08-avatax.md`) |
| `line_total` and `fee_total` computed inside `store/update`, not by model accessor | Service owns derivation |
| `generateNumber()` uses `ORD-{year}-{seq}` with zero-padded 4-digit seq | Greppable, year-resetting |
| Serial allocation locks rows (`lockForUpdate`) inside transaction | Prevents race with another concurrent order |
| Shop billing address read from `config('shop.billing')` | Configurable per environment (Houston shop default) |
| `complete()` creates ONE `inventory_movement` per line, all in same transaction | Per `14-events-inventory.md` truth table |
| `delete()` calls `AuditLogService::log($order, 'deleted')` BEFORE `$order->delete()` | Audit row must persist after CASCADE wipes the order |
| All status checks throw `DomainException` (not `RuntimeException`) | Project convention — caught by controller as 422 redirect |
| `recordCashPayment()` requires `amount === grand_total` exactly | No partial payments in ex-19 scope |
| `OrderService` constructor injects `InventoryMovementService` + `AuditLogService` | DI for testability |

---

## File location

```
app/Services/OrderService.php
```

---

## Constructor

```
__construct(
    private readonly InventoryMovementService $movements,
    private readonly AuditLogService $auditLog,
)
```

> No business logic in the constructor — just dependency injection.

---

## Public method: `store(array $data, User $createdBy): Order`

### Contract
Creates an order with lines and per-line fees in a single atomic transaction. **Does NOT allocate serials yet** — serial allocation happens at payment (see `recordCashPayment()`). Pending orders never tie up inventory.

### Inputs
- `$data` — validated payload from `StoreOrderRequest` (shape locked in `09-requests.md`)
- `$createdBy` — the `User` placing the order

### Returns
- The newly created `Order` model (with relations loaded)

### Side effects (inside `DB::transaction`)
1. INSERT `orders` row with:
   - `number` (via `generateNumber()`)
   - `status=Pending`, `payment_status=Unpaid`
   - billing snapshot (via `resolveBillingSnapshot()`)
   - shipping snapshot (via `resolveShippingSnapshot()`)
   - `shipping`, `created_by`, customer_id, source
2. For each line in `$data['lines']`:
   - INSERT `order_lines` row with:
     - `sku` + `product_name` snapshotted from listing
     - `inventory_serial_id = NULL` (allocated later at payment time)
     - `unit_price`, `tax_amount` from payload
     - `line_total` = `unit_price + tax_amount`
   - For each fee in this line's `fees` array:
     - INSERT `order_line_fees` row with:
       - `name`, `amount`, `tax_amount` from payload
       - `fee_total` = `amount + tax_amount`
       - `created_by` = `$createdBy->id`
3. UPDATE `orders.grand_total` via `recalculateTotals($order)`
4. INSERT `order_events` row: `event=OrderPlaced`, `metadata={sku, product_name, grand_total}` (first line's sku/name)
5. `AuditLogService::log($order, 'created')`

### NOT touched
- `inventory_movements` — no row
- `inventory_serials.status` — untouched (no allocation yet)
- `inventory_serials.inventory_location_id` — untouched
- `order_lines.inventory_serial_id` — stays NULL until payment
- `payments` — no row

### Edge cases (each maps to a test in `15-tests.md`)
- Any step fails → entire transaction rolls back → `it_rolls_back_all_changes_if_line_creation_fails`, `it_rolls_back_all_changes_if_fee_creation_fails`, `it_rolls_back_all_changes_if_event_insert_fails`
- Out-of-stock check happens at payment, NOT at store — `store()` succeeds even if no serial is available (admin can fix stock before payment)

### Tests covered
All 28 tests under `## store()` in `15-tests.md`.

---

## Public method: `update(Order $order, array $data): Order`

### Contract
Replaces order lines + fees with new payload values. Only allowed on `pending` orders.

### Inputs
- `$order` — existing `Order` (status must be `Pending`)
- `$data` — validated payload from `UpdateOrderRequest`

### Returns
- The updated `Order` model

### Side effects (inside `DB::transaction`)
1. Lock order via `lockForUpdate`
2. Throw `DomainException` if `status !== Pending`
3. DELETE all existing `order_lines` for this order (CASCADE wipes `order_line_fees`)
4. Recreate lines + fees from `$data` (same logic as `store()` steps 2)
5. Allocate serials (lines may have changed)
6. UPDATE `orders.grand_total` via `recalculateTotals($order)`
7. `AuditLogService::log($order, 'updated')`

### Edge cases
- Status not pending → `DomainException` → `it_throws_DomainException_when_order_not_pending`

### Tests covered
All 5 tests under `## update()` in `15-tests.md`.

---

## Public method: `delete(Order $order): void`

### Contract
Permanently deletes a pending order. Audit log row is written BEFORE the order row is removed, so audit history persists after CASCADE wipes everything.

### Inputs
- `$order` — existing `Order` (status must be `Pending`)

### Returns
- `void`

### Side effects (inside `DB::transaction`)
1. Throw `DomainException` if `status !== Pending`
2. `AuditLogService::log($order, 'deleted')` — fires FIRST
3. `$order->delete()` — hard delete, CASCADE wipes:
   - `order_lines` rows
   - `order_line_fees` rows (cascaded via `order_lines`)
   - `order_events` rows
   - `payments` rows (if any)

### Edge cases
- Status not pending → `DomainException` → `it_throws_DomainException_when_order_not_pending`
- Audit log must persist after CASCADE → `it_calls_audit_log_BEFORE_delete`

### Tests covered
All 8 tests under `## delete()` in `15-tests.md`.

---

## Public method: `recordCashPayment(Order $order, array $data, User $createdBy): Payment`

### Contract
Records a full cash payment, **allocates an in-stock serial to each line**, advances order from `pending → processing` and `unpaid → paid`. Inventory is reserved (FK only) at this point — actual sale movement + serial-status flip happens at `complete()` (handover).

> **Decision (per #6):** serial allocation moved here from `store()`. Reason: pending orders shouldn't tie up inventory that another walk-in might need. Locking happens once the customer actually pays.

### Inputs
- `$order` — existing `Order` (status `Pending`, payment_status `Unpaid`)
- `$data` — validated payload from `RecordCashPaymentRequest` (just `amount`)
- `$createdBy` — the `User` recording the payment

### Returns
- The newly created `Payment` model

### Side effects (inside `DB::transaction`)
1. Lock order via `lockForUpdate`
2. Validate:
   - `status === Pending` (else `DomainException`)
   - `payment_status === Unpaid` (else `DomainException`)
   - `amount === grand_total` exactly (else `DomainException` — no partial)
3. **Allocate + sell** each line's serial:
   - For each `order_line` where `inventory_serial_id IS NULL`:
     - Call `allocateSerial($line)` (private helper, uses `lockForUpdate`)
     - UPDATE `order_lines.inventory_serial_id = $serial->id`
   - For each line: call `InventoryMovementService::recordSale($line->inventory_serial_id, $order, $createdBy, "cash sale at counter")`
     - This creates the `inventory_movements` row (type=sale, from=serial's location, to=null, ref=order.number)
     - This flips `inventory_serials.status` → `Sold` and nulls `inventory_location_id`
   - If any line has no available serial → `DomainException` (rolls back; no payment recorded)
4. INSERT `payments` row:
   - `order_id`, `payable_type='order'`, `payable_id=$order->id`
   - `method=Cash`, `amount=$data['amount']`, `status=Paid`
   - `cash_received_at=now()`
   - `created_by=$createdBy->id`
5. UPDATE `orders.payment_status` → `Paid`
6. UPDATE `orders.status` → `Processing`
7. INSERT `order_events` row: `event=PaymentReceived`, `metadata={method:"cash", amount, shipping}`
8. `AuditLogService::log($order, 'payment_recorded')`

> **Decision:** for cash walk-in, "payment" = "sale" because the customer is physically at the counter receiving the unit. We record the inventory movement + sold-status here rather than at `complete()`. Keeps the audit trail accurate: serial is sold the moment the customer pays. See `14-events-inventory.md`.

### NOT touched
- `order_lines` / `order_line_fees` amounts — unchanged (only `inventory_serial_id` is set)

### Edge cases
- Already paid → `DomainException` → `it_throws_DomainException_when_order_already_paid`
- Wrong status → `DomainException` → `it_throws_DomainException_when_order_status_not_pending`
- Amount mismatch → `DomainException` → `it_throws_DomainException_when_amount_does_not_match_grand_total`
- No in-stock serial available → `DomainException` (NEW — moved from store) → `it_throws_when_no_in_stock_serial_at_payment`
- UNIQUE serial constraint conflict → `DomainException` → `it_throws_when_unique_constraint_blocks_double_allocation_at_payment`

### Tests covered
All 14 tests under `## recordCashPayment()` in `15-tests.md`.

---

## Public method: `complete(Order $order, User $completedBy): Order`

### Contract
Finalizes a processing order. **Inventory is already moved at payment** (see `recordCashPayment()`); this method only transitions the status to `Complete`, fires the `completed` event, and writes the audit log. Method retained because non-cash flows (future) may legitimately separate payment from finalization.

### Inputs
- `$order` — existing `Order` (status must be `Processing`)
- `$completedBy` — the `User` finalizing the order

### Returns
- The updated `Order` model

### Side effects (inside `DB::transaction`)
1. Lock order via `lockForUpdate`
2. Throw `DomainException` if `status !== Processing`
3. UPDATE `orders.status` → `Complete`
4. INSERT `order_events` row: `event=Completed`, `metadata={}`
5. `AuditLogService::log($order, 'completed')`

### NOT touched
- `inventory_movements` — NO new row (was created at payment)
- `inventory_serials` — NO change (already `Sold` from payment)
- `payments` — unchanged
- `order_lines` / `order_line_fees` — unchanged

### Edge cases
- Status not processing → `DomainException` → `it_throws_DomainException_when_order_status_not_processing`

### Tests covered
All 11 tests under `## complete()` in `15-tests.md`.

---

## Private helper: `generateNumber(): string`

### Contract
Returns the next order number in the format `ORD-{year}-{4-digit-seq}` (e.g., `ORD-2026-0019`).

### Behavior
- Year resets the sequence (new year → `0001`)
- Atomic — uses `DB::transaction` + `lockForUpdate` on the latest row OR DB-level sequence
- Sequence is per-year, not global

### Edge cases
- Concurrent creates → DB locking ensures no duplicates → `it_generates_order_number_in_format_ORD-{year}-{seq}`

---

## Private helper: `recalculateTotals(Order $order): void`

### Contract
Re-reads all `order_lines` (and their `order_line_fees`) for the order and updates `orders.grand_total`.

### Formula
```
orders.grand_total = SUM(order_lines.line_total) + SUM(order_line_fees.fee_total) + orders.shipping
```

### Side effects
- UPDATE `orders.grand_total`

### Tests covered
- `it_computes_grand_total_as_sum_of_line_totals_plus_fee_totals_plus_shipping`
- `it_recalculates_grand_total_after_line_changes`

---

## Private helper: `assignSerialsToLines(array $lines): array`

### Contract
For each line in the payload, finds an available in-stock `inventory_serial` for the line's `product_listing.product`. Returns array mapping line index → serial id.

### Behavior
- Inside `DB::transaction` + `lockForUpdate` on candidate serials
- Excludes serials already allocated to other order_lines (`whereNotIn` subquery against `order_lines.inventory_serial_id`)
- Picks the oldest in-stock serial (FIFO)

### Edge cases
- No available serial for a line's product → `DomainException`

---

## Private helper: `recordSaleMovements(Order $order, User $by): void`

### Contract
For each line on the order, delegates to `InventoryMovementService::recordSale()` and flips the serial's status to `Sold`.

### Inputs
- `$order` — the order being completed
- `$by` — the user completing the order

### Behavior per line
- Call `$this->movements->recordSale($line->inventory_serial_id, $order, $by)` (or similar — exact signature defined by existing `InventoryMovementService`)
- UPDATE serial status to `Sold`

---

## Private helper: `resolveBillingSnapshot(?int $addressId, PaymentMethod $method): array`

### Contract
Returns associative array of 10 `billing_*` fields based on payment method.

### Behavior (precedence order — customer choice always wins)
1. If `$addressId` is provided (not null) → look up `CustomerAddress::find($addressId)` and use ITS fields. Applies to ANY payment method, including Cash.
2. Else if `PaymentMethod::Cash` → return `config('shop.billing')` array. **If shop config is unset (env vars not configured), every field in the snapshot is `null`. No hardcoded fallback name.**
3. Else → return empty array (all null)

> **Bug fix:** previously Cash payment always overrode the chosen `billing_address_id` with shop billing, causing "Not provided" / shop address to show even when admin picked a real customer address. The fix puts customer choice ahead of payment-method default.

### Returns
When shop configured:
```
[
    'billing_first_name' => 'ACME Tuning',     // from SHOP_BILLING_NAME
    'billing_last_name'  => null,
    'billing_email'      => 'sales@acme.com',  // from SHOP_BILLING_EMAIL
    // ... 7 more
]
```

When shop NOT configured (all env vars unset):
```
[
    'billing_first_name' => null,
    'billing_last_name'  => null,
    'billing_email'      => null,
    // ... all 10 fields null
]
```

### Tests covered
- `it_sets_billing_snapshot_to_shop_address_for_cash` (shop configured)
- `it_sets_billing_snapshot_to_null_when_shop_config_unset` (shop NOT configured)

---

## Private helper: `resolveShippingSnapshot(?int $addressId): array`

### Contract
Returns associative array of 10 `shipping_*` fields.

### Behavior
- `$addressId === null` → return array with all 10 keys set to `null` (in-store pickup)
- Otherwise → fetch `CustomerAddress` and return its fields prefixed with `shipping_` (out of scope for ex-19)

### Tests covered
- `it_sets_shipping_snapshot_to_null_for_pickup`

---

## Config dependency

`config/shop.php` must define the shop's billing address used by `resolveBillingSnapshot()`:

```php
return [
    'billing' => [
        'first_name'    => env('SHOP_BILLING_NAME'),
        'email'         => env('SHOP_BILLING_EMAIL'),
        'phone'         => env('SHOP_BILLING_PHONE'),
        'address_line1' => env('SHOP_BILLING_LINE1'),
        'city'          => env('SHOP_BILLING_CITY'),
        'state'         => env('SHOP_BILLING_STATE'),
        'postal_code'   => env('SHOP_BILLING_ZIP'),
        'country'       => env('SHOP_BILLING_COUNTRY'),
    ],
];
```

> **No hardcoded defaults.** Every key returns `null` when its env var is unset. This makes the system multi-tenant friendly — shop name only appears if the operator explicitly configures it.

> Plan deliverable: `config/shop.php` + matching `.env.example` entries.

---

## Dependencies

**Depends on:**
- `01-enums.md` — all 5 enums for status/source/method
- `03-schema.md` — all 5 tables
- `04-models.md` — all 5 models
- `06-policy.md` — caller (controller) authorizes before service is invoked
- `14-events-inventory.md` — every method matches the truth table
- `15-tests.md` — every test in `OrderServiceTest` drives behavior
- Existing: `InventoryMovementService::recordSale()`, `AuditLogService::log()`

**Depended on by:**
- `11-controller.md` — every controller action delegates to a service method

---

## Validation gates

- [ ] Every public method wraps work in `DB::transaction()`
- [ ] Every public method's edge cases map to a test name in `15-tests.md`
- [ ] No method body in this plan file (contract-only)
- [ ] `delete()` calls `AuditLogService::log()` BEFORE `$order->delete()`
- [ ] `complete()` creates `inventory_movements` AND flips serial — both, atomically
- [ ] `recordCashPayment()` does NOT create `inventory_movements` (per `14-events-inventory.md`)
- [ ] `store()` does NOT create `inventory_movements` (per `14-events-inventory.md`)
- [ ] All status guard violations throw `DomainException`
- [ ] All allocation conflicts throw `DomainException`
- [ ] Shop billing pulled from `config('shop.billing')`, not hardcoded in code

---

## Cross-check vs Layer 1 + 2 + 3

| Layer 1 truth | Service method satisfies |
|---------------|--------------------------|
| `14-events-inventory.md` — `order_placed` fires after `OrderService::store()` succeeds | `store()` step 4 |
| `14-events-inventory.md` — `payment_received` fires after `recordCashPayment()` succeeds | `recordCashPayment()` step 6 |
| `14-events-inventory.md` — `completed` fires after `complete()` succeeds | `complete()` step 5 |
| `14-events-inventory.md` — serial allocated at `store`, status changes only at `complete` | `store()` allocates FK; `complete()` flips status |
| `14-events-inventory.md` — atomic invariant | Every public method wraps in `DB::transaction` |
| `06-policy.md` — only pending orders deletable | `delete()` throws if not pending |
| `06-policy.md` — only pending orders payable | `recordCashPayment()` throws if not pending |
| `06-policy.md` — only processing orders completable | `complete()` throws if not processing |
| `15-tests.md` — 69 unit tests | All 5 public + 6 private methods provide the surface to test |
| `16-audit-log.md` — `AuditLog::log()` on every state-changing method | `store`/`update`/`delete`/`recordCashPayment`/`complete` all call it |

No gaps. Every test target has a method + contract.
