# Orders Plan — Overview

> **Single source of truth:** [`../system-design/examples/ex-19-walkin-cash-instore-pickup.md`](../system-design/examples/ex-19-walkin-cash-instore-pickup.md)
>
> This plan delivers the Orders module that supports **exactly the flow demonstrated in ex-19** — walk-in cash, in-store pickup, ECM with per-line fees (Programming + Gas Tuning), shop-as-billing convention. Nothing more, nothing less.

---

## Goal

Build the Orders module so that the ex-19 scenario can be created, paid, completed, and reported end-to-end — every table row, every event, every status transition matching the fixture exactly.

---

## Scope (what this plan covers)

| Concern | In scope |
|---------|---------|
| **Source** | `walk_in` only |
| **Payment method** | `cash` only |
| **Delivery** | In-store pickup only (no carrier) |
| **Order lines** | 1..N line items per order, each with a product_listing + serial |
| **Per-line fees** | `order_line_fees` table — Programming, Gas Tuning, etc. with AvaTax tax |
| **Billing snapshot** | Shop address convention for cash sales — pulled from `config('shop.billing')`. **Optional:** when env vars unset, snapshot stores `null` for every billing field, receipt skips letterhead, AvaTax skips API call. No hardcoded shop name anywhere. |
| **Shipping snapshot** | NULL for pickup |
| **Tax** | AvaTax — populates `tax_amount` per line + per fee |
| **Status flow** | `pending → processing → complete` |
| **Order events** | `order_placed, payment_received, completed` |
| **Inventory** | Serial assignment on order, `sale` movement at handover, serial status `in_stock → sold` |
| **Payments** | Single cash payment row, polymorphic to order |
| **Audit log** | `AuditLogService::log()` called on create/update/payment/complete/delete |
| **Notifications** | None — walk-in customer is at counter; no email needed |
| **Receipts** | Print-friendly Blade view (browser print + emailable HTML) |
| **Permissions** | Role × permission matrix (admin, sales, manager) |
| **Policy** | OrderPolicy enforces all gates |
| **Routes** | Admin `/admin/orders/*` + helper endpoints |
| **Views** | index, create, edit, show — Alpine state with per-line fee repeater |

> **Anything not listed in Scope above is out of scope.** No exceptions.

---

## Plan files (19 total)

```
.claude/plans/Orders/
  ├── 00-overview.md              ← this file
  │
  │  Layer 1 — Foundation (no dependencies)
  ├── 01-enums.md                  ← OrderStatus, OrderSource, PaymentMethod, PaymentStatus, OrderEvent
  ├── 02-permissions.md            ← role × permission matrix
  ├── 03-schema.md                 ← migrations (orders, order_lines, order_line_fees, order_events)
  ├── 14-events-inventory.md       ← truth table: event → inventory_movement → serial status
  │
  │  Layer 2 — Spec (depends on layer 1)
  ├── 15-tests.md                  ← TDD spec — every test is a requirement
  │
  │  Layer 3 — Models (depends on schema + enums)
  ├── 04-models.md                 ← Order, OrderLine, OrderLineFee, OrderEvent
  ├── 05-factories.md              ← factories + named states (pending, paid, complete, withFees, walkInCash)
  ├── 06-policy.md                 ← OrderPolicy gates
  │
  │  Layer 4 — Behavior (depends on layers 1-3)
  ├── 07-service.md                ← OrderService — store, update, recordCashPayment, complete, recalculateTotals, assignSerialsToLines, recordSaleMovements
  ├── 08-avatax.md                 ← AvaTaxService — sends fees as additional AvaTax lines
  ├── 09-requests.md               ← StoreOrderRequest, UpdateOrderRequest, RecordCashPaymentRequest
  ├── 10-routes.md                 ← /admin/orders/* + helper endpoints
  ├── 11-controller.md             ← OrderController actions
  │
  │  Layer 5 — Presentation + integration (depends on all above)
  ├── 12-views.md                  ← index, create, edit, show + per-line fee Alpine repeater
  ├── 13-seeders.md                ← OrderPermissionSeeder + demo OrderSeeder using ex-19 fixture
  ├── 16-audit-log.md              ← AuditLogService integration
  └── 18-receipts.md               ← Print-friendly Blade receipt view
```

> **Note:** `17-notifications.md` is OMITTED — out of scope for this iteration.

---

## Decisions LOCKED at overview level (details in respective files)

| Decision | Rationale | Where detailed |
|----------|-----------|----------------|
| `orders` table has only `shipping` + `grand_total` totals (no subtotal/line_fees/tax columns) | Receipt-style math — every row carries its all-in total | `03-schema.md` |
| `order_lines.tax_rate` column DROPPED | AvaTax is source of truth — only store `tax_amount` | `03-schema.md` |
| `order_line_fees.fee_total` STORED column | Matches `order_lines.line_total` pattern | `03-schema.md` |
| `order_line_fees.created_by` STORED | Audit trail for which staff entered each fee | `03-schema.md` |
| `order_fees` table NEVER created | Order-level Service Fee is a non-goal — per-line only | — |
| Billing snapshot = shop address for cash | Records WHERE sale physically occurred | `07-service.md` |
| Shipping snapshot = NULL for pickup | No carrier, no destination | `07-service.md` |
| AvaTax ship-to = ship-from (shop) for pickup | Store-local rate applied | `08-avatax.md` |
| `processing` at payment, `complete` at handover | Can be hours apart (technician work) | `07-service.md` |
| `sale` inventory movement fires at handover (not payment) | Inventory mirrors physical reality | `07-service.md`, `14-events-inventory.md` |
| AvaTax called ONCE per order (with units + fees as separate lines) | Single API call, response maps to rows | `08-avatax.md` |
| Notifications: NONE for walk-in counter sales | Customer is physically present | — |
| Receipts: print-friendly Blade view only | No PDF dependency for this iteration | `18-receipts.md` |
| Audit log: every state-changing OrderService method | Compliance + dispute trail | `16-audit-log.md` |

---

## Edge cases (preview — enumerated in respective files)

These are answered in detail in the relevant file. Listed here so nothing is forgotten:

| Edge case | Detailed in |
|-----------|-------------|
| Customer is `tax_exempt = true` → AvaTax returns zeros | `08-avatax.md` |
| AvaTax timeout / error → return zeros, log warning | `08-avatax.md` |
| Two staff edit same pending order → optimistic lock | `07-service.md` |
| Order line removed during edit → CASCADE deletes its `order_line_fees` rows | `03-schema.md` |
| Serial assigned to line but inventory_movement not yet created → fire only on handover | `07-service.md`, `14-events-inventory.md` |
| Cash payment recorded but unit not yet handed over → status stays `processing` | `07-service.md` |
| Order has 0 lines on submit → validation fails | `09-requests.md` |
| Fee `amount = 0` → allowed (some fees may be waived) | `09-requests.md` |
| AvaTax returns negative or NaN → reject + log | `08-avatax.md` |
| `unit_price` changed during edit → re-fetch AvaTax | `07-service.md`, `12-views.md` (Alpine debounce) |
| Order hard-deleted (only pending) → `AuditLogService::log()` fires BEFORE delete, then CASCADE wipes lines + fees + events | `16-audit-log.md` |

---

## Success criteria

The Orders module is "done" when the ex-19 scenario can be created, paid, and completed end-to-end via the UI — every table row, every event, every status transition matching the fixture exactly.

---

## Dependencies (other modules — already built, used here)

| Module | What we use |
|--------|-------------|
| Customer | `App\Models\Customer`, `tax_exempt` flag |
| Product / ProductListing | `App\Models\ProductListing`, `currentPrice()` |
| InventoryLocation / InventorySerial | Serial assignment, status transitions |
| InventoryMovementService | `recordSaleMovements()` integration |
| AvaTaxService | `calculateTax()` — will be extended to handle per-fee tax |
| AuditLogService | `log()` calls in OrderService |
| User / Role / Permission (Spatie) | Role × permission matrix, policy authorization |
