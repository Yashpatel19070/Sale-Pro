# TDD Plan — Cash + Counter Order Lifecycle

> Spec = [ex-000-master-cash-counter.md](./ex-000-master-cash-counter.md). Rules = [../global.md](../global.md) + project `CLAUDE.md`.
> Build order = master §7. Every row of master data = a test fixture. **Schema is locked — do not redesign here.**

---

## 0. Conventions (non-negotiable — every file)

- **Flow:** Request → **FormRequest** → Controller → **Service** → Model → Response. Controllers thin; logic in services.
- `declare(strict_types=1);` on **every** PHP file.
- Controllers use **`$request->validated()`** only — never `$request->all()`.
- Eager load with **`with()`** — never lazy load (no N+1).
- **`DB::transaction`** around **every multi-table write** (all branches write ≥2 tables).
- **Immutable** — return new objects / fresh model instances; no in-place mutation of shared state.
- **Enums in PHP** — backed enum + model `$casts` + FormRequest `Rule::enum`. DB column = plain `string`. New value = PHP change, no migration.
- **Numbers** via `NumberSequenceService` (DB sequence / `INSERT … ON DUPLICATE KEY` counter row, `lockForUpdate`) — **never `SELECT max()+1`**.
- **Money** USD, exact, 2 dp; tax always from `AvaTaxService` (never hand-entered).
- **Authorization** in `FormRequest::authorize()` → `$this->user()->can(...)` (Spatie role/permission); deny → 403 (404 for cross-parent nested per scope-binding rule).
- **TOCTOU guards inside the transaction** (re-check serial status / payment_status under `lockForUpdate`).

### Definition of done (per slice)
- [ ] RED: failing Pest test written first
- [ ] GREEN: minimal code passes
- [ ] REFACTOR: Pint clean, PHPStan lvl 8, no N+1
- [ ] Feature test **per controller action** · unit test **per service method**
- [ ] 80%+ coverage on touched code
- [ ] STATUS.md updated

---

## 1. Migrations (build first — order matters for FKs)

| # | table | key cols / notes | indexes / locks |
|---|---|---|---|
| 1 | `customers` | (exists) | — |
| 2 | `customer_addresses` | (exists) | `customer_id` |
| 3 | `number_sequences` | `module` (PK), `year`, `counter` BIGINT | row lock per module |
| 4 | `inventory_serials` | `serial` (natural PK), `status`, `location` | `status`, unique `serial` |
| 5 | `inventory_movements` | append-only; `serial`, `type`, `from`,`to`,`reference` | `serial`, `reference` |
| 6 | `orders` | `number`, `status`, `payment_status`, `origin`, `source`, `grand_total`, billing/shipping snapshot, `completed_at/by`, `closed_at` | unique `number`, `customer_id`, `status` |
| 7 | `order_lines` | `inventory_serial_id`, `unit_price`, `tax_amount`, `line_total` | `order_id`, `inventory_serial_id` |
| 8 | `order_line_fees` | `amount`, `tax_amount`, `fee_total` | `order_line_id` |
| 9 | `payments` | poly `payable_type/id`, `kind`, `method`, `status`, `received_at/by` | `(payable_type,payable_id)`, `order_id` |
| 10 | `order_events` | `event`, `metadata` json, append-only | `order_id`, `event` |
| 11 | `order_notes` | `type`, `body`, `deleted_at` (SoftDeletes) | `order_id` |
| 12 | `complaints` | `number`, `order_line_id`, `serial`, `examination_result`, `unit_outcome` | unique `number`, `order_id` |
| 13 | `replacements` | `number`, `parent_id` (self-FK), `complaint_id`, `type`, `charge`, `pay_status` | unique `number`, `order_id`, `parent_id` |
| 14 | `replacement_lines` | `old_serial`, `new_serial` | `replacement_id` |
| 15 | `returns` | `number`, `complaint_id` (nullable), `reason`, `status` | unique `number`, `order_id` |
| 16 | `return_lines` | `serial`, `condition`, `restock` | `return_id` |
| 17 | `refunds` | `number`, `return_id` (nullable), `reason`, `total_amount`, `total_tax`, `status` | unique `number`, `order_id`, `return_id` |
| 18 | `refund_lines` | `order_line_id`, `amount`, `tax` | `refund_id` |

> `decimal(10,2)` for money (use `->decimal()`, not removed `unsignedDecimal`). All status/enum cols = `string`. Every table: `created_by`, `created_at`. `delivered_at/by` **not** added (deferred).

**Test:** migration test — all tables exist, FK constraints, unique on `number`.

---

## 2. Models + Enums

**Enums (PHP backed, `app/Enums/`):** `OrderStatus` · `OrderPaymentStatus` (unpaid/paid) · `OrderSource` · `OrderOrigin` · `SerialStatus` · `MovementType` · `OrderEvent` · `OrderNoteType` · `PaymentMethod` · `PaymentStatus` (paid/refunded) · `PaymentKind` · `ComplaintStatus` · `ExaminationResult` · `UnitOutcome` · `ReplacementType` · `ReplacementPayStatus` · `ReplacementStatus` · `ReturnStatus` · `ReturnReason` · `ReturnLineCondition` · `RestockType` · `RefundStatus` · `RefundReason`. **(23)**

> **Not enums:** `payments.payable_type` (`order`/`replacement`/`refund`) = polymorphic **morph type** → `Relation::enforceMorphMap([...])`, not a backed value enum.

**Models:** match tables. `$casts` map each enum + money to `decimal:2`. Relationships with **`with()`** defaults where safe. `Order` uses spatie `LogsActivity` (`logFillable()->logOnlyDirty()`); `status`/`payment_status`/`grand_total` **not fillable** (set via `forceFill` in service) → `order_events` carries those transitions.

**Test:** unit — casts return enum instances; money is 2-dp; relationship eager-loads.

---

## 3. Permission matrix (Spatie roles: admin · manager · sales)

| ability (Spatie permission) | admin | manager | sales |
|---|:--:|:--:|:--:|
| `order.create` / `order.process` / `order.pay` / `order.complete` | ✅ | ✅ | ✅ |
| `order.cancel` (unpaid) | ✅ | ✅ | ✅ |
| `order.cancelRefund` (paid) | ✅ | ✅ | ❌ |
| `complaint.create` | ✅ | ✅ | ✅ |
| `complaint.examine` (tech inspect) | ✅ | ✅ | ✅ |
| `complaint.decide` / `replacement.issue` (free) | ✅ | ✅ | ✅ |
| `replacement.charge` (charged rep) | ✅ | ✅ | ❌ |
| `return.create` / `return.inspect` | ✅ | ✅ | ✅ |
| `return.approve` / `refund.issue` | ✅ | ✅ | ❌ |
| `note.create` (private/customer) | ✅ | ✅ | ✅ |
| `note.softDelete` (own + any) | ✅(any) | ✅(any) | ✅(own) |
| `note.forceDelete` | ✅ | ❌ | ❌ |

> Checked in `FormRequest::authorize()` via `$this->user()->can('ability')`. Seed permissions + assign to roles in a seeder (+ test: sales gets 403 on `order.cancelRefund`, `refund.issue`, `replacement.charge`, `note.forceDelete`).

---

## 4. Services (unit-tested, one test per public method)

| service | methods | writes | tests |
|---|---|---|---|
| `NumberSequenceService` | `next(module): string` | `number_sequences` | concurrent `next()` no dup (lockForUpdate); pad ≥4; year label only |
| `InventoryService` | `reserve(serials, order)` · `release(serials)` · `sell(serials, ref)` · `intake(serial, ref)` · `toRebuild(serial)` · `backToStock(serial)` · `move(...)` | `inventory_serials`, `inventory_movements` | reserve locks serial (one order); release writes **no** movement; sell writes movement; status transitions valid |
| `AvaTaxService` (exists) | `calculateTax()` · `commitInvoice()` · `adjustTransaction()` | — (API) | quote uncommitted; commit on pay; adjust on refund; free rep skips |
| `OrderService` | `place(data)` · `forceProcess(order)` · `markReady(order)` · `recordPayment(order, method)` · `complete(order)` · `cancel(order)` | orders, lines, fees, serials, events, payments | grand_total math; reserve before pay; **complete requires paid**; cancel only unpaid+reserved |
| `PaymentService` | `pay(payable, method, amount)` · `refund(payable, amount, method)` | payments | exact full = grand_total; refund row `kind=refund` |
| `OrderEventService` | `record(order, event, meta)` | order_events | append-only; correct event enum |
| `OrderNoteService` | `add` · `softDelete` · `forceDelete` | order_notes | permission-gated; soft vs force |
| `ComplaintService` | `open(order, line, serial, issue)` *(Gate-1 intake verify — serial belongs to order — done inside `open`)* · `examine(complaint, result, notes)` · `close(complaint, outcome)` | complaints, serials, movements, events | pipeline order enforced; serial→under_examination; no skip |
| `ReplacementService` | `issueFree(complaint, newSerial)` · `issueCharged(complaint, newSerial, charge)` | replacements, replacement_lines, serials, payments, events | requires complaint; free=no pay; charged=pay before handover; `parent_id` chain; old→rebuild/back_to_stock |
| `ReturnService` | `request(order, line, serial, reason)` · `inspect(return, condition)` · `approve(return)` | returns, return_lines, serials, movements, events | verify serial; good→back_to_stock; refund only after received |
| `RefundService` | `forReturn(return, lines)` · `forCancel(order)` | refunds, refund_lines, payments, events, AvaTax adjust | return=unit only (fees kept); cancel=unit+fees; `return_id` set/NULL |

> **Every method:** `DB::transaction` if multi-table; TOCTOU re-check inside (e.g. serial still `reserved`, payment still `unpaid`) under `lockForUpdate`.

---

## 5. Per-branch slices (controller + request + service + view + tests)

> Order = build order. Each slice = RED→GREEN→REFACTOR. Assertions condensed from master §7.

### Spine — place → process → pay → complete
- **Routes** (`/admin/orders`): `POST /` (create) · `POST /{order}/process` · `POST /{order}/ready` · `POST /{order}/payment` · `POST /{order}/complete`
- **Requests:** `StoreOrderRequest` (lines+fees+customer, `order.create`) · `ProcessOrderRequest` (`order.process`) · `MarkReadyRequest` (`order.process`) · `RecordPaymentRequest` (method+amount, `order.pay`) · `CompleteOrderRequest` (`order.complete`)
- **Controller:** `Admin\OrderController@store/process/ready/payment/complete` → `OrderService`
- **Service:** `place → forceProcess → markReady → recordPayment → complete` (+ `AvaTaxService`, `InventoryService`, `OrderEventService`)
- **Views:** order builder (Woo-style: listing/sku/location/stock readonly, price+tax editable, fees) · order show (timeline from `order_events` + notes rail) · receipt (shows `orders.shipping` only)
- **Feature tests:** create→lines+fees+quote; process→serials reserved while unpaid; pay→`paid`+commit; complete **blocked until paid** (assert 422/guard); complete→serials sold + sale movement + `completed`
- **Unit tests:** `OrderService::place/forceProcess/recordPayment/complete`, `InventoryService::reserve/sell`, `NumberSequenceService::next`

### B1 — cancel (unpaid)
- **Route:** `POST /{order}/cancel` · **Request:** `CancelOrderRequest` (`order.cancel`)
- **Service:** `OrderService::cancel` → guard `unpaid` + serials `reserved`; `InventoryService::release`; event `order_cancelled`
- **Feature:** cancel unpaid → serials `in_stock`, **no payment/movement row**, status `cancelled`; cancel **paid** order via this route → 422 (must use cancel-refund)
- **Unit:** `cancel` releases serials, writes no movement; rejects paid order

### B2 — cancel-refund (paid)
- **Route:** `POST /{order}/cancel-refund` · **Request:** `CancelRefundRequest` (`order.cancelRefund` — manager/admin)
- **Service:** `RefundService::forCancel(order)` (full incl. fees, `reason=cancel`, `return_id=NULL`) + `PaymentService::refund` + `AvaTaxService::adjustTransaction` (full) + `InventoryService::release` + cancel
- **Feature:** full refund = grand_total (fees incl.); `refunds.return_id=NULL`; payments refund row; serials released; `payment_status` stays `paid`; net $0; **sales → 403**
- **Unit:** `RefundService::forCancel` amount = unit+fees per line; AvaTax full adjust called

### B3 — complaint → free replacement
- **Routes:** `POST /complaints` · `POST /complaints/{c}/examine` · `POST /complaints/{c}/replacement` (free)
- **Requests:** `StoreComplaintRequest` (`complaint.create`) · `ExamineComplaintRequest` (`complaint.examine`) · `IssueReplacementRequest` (`replacement.issue`)
- **Services:** `ComplaintService::open/verify/examine/close` + `ReplacementService::issueFree`
- **Feature:** complaint required (no standalone rep → 422); fault→`free`; old→`to_rebuild`; new reserve→sold; **no payment row**; 4 events
- **Unit:** `issueFree` sets `parent_id=NULL`, old→rebuild, new→sold, no payment

### B5 — complaint → no_fault, return to cx
- **Route:** reuse complaint examine + `POST /complaints/{c}/return-unit` (`complaint.decide`)
- **Service:** `ComplaintService::close(outcome=returned_to_customer)` → serial `under_examination → sold` (same cx); **no replacement/payment/new serial**
- **Feature:** same unit round-trip `sold→under_examination→sold`; `unit_outcome=returned_to_customer`; no `replacements` row
- **Unit:** close-as-returned writes no replacement

### B4 — complaint → charged replacement (no_fault insist)
- **Route:** `POST /complaints/{c}/replacement-charged` (`replacement.charge` — manager/admin)
- **Service:** `ReplacementService::issueCharged` → AvaTax quote → **pay first** (`PaymentService::pay`, `payable_type=replacement`) → commit (REP code) → old→`back_to_stock`, new→sold
- **Feature:** no_fault+insist→`charged`; **pay before handover** (assert serial not sold until paid); `payable_type=replacement`; old→`in_stock`; **sales→403**
- **Unit:** `issueCharged` blocks handover until `pay_status=paid`; AvaTax commit code=REP number

### B6 — return + refund (changed mind)
- **Routes:** `POST /returns` · `POST /returns/{r}/inspect` · `POST /returns/{r}/approve-refund`
- **Requests:** `StoreReturnRequest` (`return.create`) · `InspectReturnRequest` (`return.inspect`) · `ApproveRefundRequest` (`refund.issue` — manager/admin)
- **Services:** `ReturnService::request/inspect/approve` + `RefundService::forReturn` + `AvaTaxService::adjustTransaction` (unit tax only) + `PaymentService::refund`
- **Feature:** `returns`+`return_lines` (goods) + `refunds`+`refund_lines` (money) linked via `return_id`; refund = **unit+tax only, fees kept**; good→`back_to_stock`; refund **only after** received; **sales→403** on approve
- **Unit:** `forReturn` amount=unit only; `inspect` good→back_to_stock / faulty→rebuild

### B-CHAIN — replacement chain (multi-round)
- No new endpoints — repeats B3/B4 on same `order_line` slot.
- **Service:** `ReplacementService::issueFree/issueCharged` sets `parent_id` = latest rep on slot; root `order_id` on every rep
- **Feature:** R1 free (`parent_id=NULL`) → R2 free (`parent_id=R1`) → R3 charged (`parent_id=R2`); `count(*) where order_id` = 3 (flat); slot serial walks; no deadlock under concurrent (lock order serial→order)
- **Unit:** `parent_id` = prior rep id; flat count; chain depth N has no recursion
- **Note:** "no deadlock under concurrency" = architectural guarantee (fixed lock order serial→order, DB sequence, append-only movements) — asserted by design, not a unit test

---

## 6. Factories / fixtures

- `CustomerFactory`, `InventorySerialFactory` (state `inStock`/`reserved`/`sold`), `OrderFactory` (states `pending`/`processing`/`completed`/`cancelled`), `OrderLineFactory`, `OrderLineFeeFactory`, `ComplaintFactory`, `ReplacementFactory`, `ReturnFactory`, `RefundFactory`.
- **Master data = canonical fixture values** (ECM 200/16.50, TCM 180/14.85, 3 fees, grand 525.01). Mock `AvaTaxService` to return master tax numbers (no live API in tests).
- Helper: `completedOrder()` (spine result) for B3–B6/B-CHAIN starting state.

---

## 7. Route table (admin side, `/admin` prefix, middleware `auth,load_perms,verified,active`)

```
GET    /admin/orders                                 order.index    (read; order.view)
GET    /admin/orders/create                           order.create   (builder view; order.create)
GET    /admin/orders/{order}                          order.show     (timeline + notes; order.view)
GET    /admin/complaints/{complaint}                  complaint.show (order.view)
GET    /admin/returns/{return}                        return.show    (order.view)
POST   /admin/orders                                 order.store
POST   /admin/orders/{order}/process                 order.process
POST   /admin/orders/{order}/ready                    order.ready    (ability: order.process)
POST   /admin/orders/{order}/payment                 order.payment
POST   /admin/orders/{order}/complete                order.complete
POST   /admin/orders/{order}/cancel                  order.cancel
POST   /admin/orders/{order}/cancel-refund           order.cancelRefund
POST   /admin/complaints                              complaint.store
POST   /admin/complaints/{complaint}/examine         complaint.examine
POST   /admin/complaints/{complaint}/replacement     complaint.replacementFree
POST   /admin/complaints/{complaint}/replacement-charged  complaint.replacementCharged
POST   /admin/complaints/{complaint}/return-unit     complaint.returnUnit
POST   /admin/returns                                return.store
POST   /admin/returns/{return}/inspect               return.inspect
POST   /admin/returns/{return}/approve-refund        return.approveRefund
POST   /admin/orders/{order}/notes                   note.store
DELETE /admin/notes/{note}                           note.softDelete
DELETE /admin/notes/{note}/force                     note.forceDelete
```
> Nested complaint/return routes use `scopeBindings()` → cross-parent = 404 (not 403).
> Route names ≠ permission abilities (e.g. `complaint.returnUnit` route checks `complaint.decide`; `order.ready` route checks `order.process`). GET reads use `order.view` (all roles).

---

## 8. Build order + checklist

1. Migrations (§1) + `number_sequences` → migration test green
2. Enums + Models + casts (§2) → model unit tests
3. Permissions seeder + matrix (§3) → 403 tests
4. `NumberSequenceService` + `InventoryService` + AvaTax mock (§4) → unit tests
5. **Spine** (place→process→pay→complete) → feature + unit
6. **B1** cancel → 7. **B2** cancel-refund → 8. **B3** free rep → 9. **B5** return-to-cx → 10. **B4** charged rep → 11. **B6** return+refund → 12. **B-CHAIN** chain
13. Views per slice (builder, show/timeline, receipt, complaint/return panels)
14. Full suite green, 80%+, Pint + PHPStan lvl 8 → update STATUS.md

> Each numbered step = its own `/create` TDD run. RED first, GREEN minimal, REFACTOR. One feature test per controller action, one unit test per service method — no exceptions.
