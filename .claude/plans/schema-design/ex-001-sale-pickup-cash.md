# ex-001 — ORD-2026-0020 — Phone Order, 2 Units + 3 Fees, In-Store Pickup, Cash at Pickup

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

**Scenario:** Marcus Webb calls the Houston shop. Orders **two units** by phone — one **Engine Control Module (ECM-2024)** and one **Transmission Control Module (TCM-2024)** — with **3 fees**: ECM needs Programming + Gas Tuning, TCM needs Programming. Staff takes the order, starts processing, and **assigns two serials** (SN-201, SN-301), which move to `reserved`. A few hours later the units are staged and a `ready_for_pickup` event fires. Marcus comes in, picks up both, and **pays $525.01 cash at the counter**.

> _Example only — the goal is to understand the data flow. IDs, order numbers, and timestamps are illustrative._

**Key aspects:**
- `source=phone` + `method=cash`, payment collected **at pickup, not at placement**
- **2 order lines, 2 distinct serials** — each line allocates its own unit
- **3 per-line fees** (ECM: 2, TCM: 1) — each with own tax
- **Billing snapshot = customer's billing address** (simple — the customer's own address)
- **Shipping snapshot = shipping address** — NULL here, in-store pickup (nothing to ship)
- **Readiness = order_event only**, status stays `processing` (D1)
- **Serials `reserved` during the assignment→pickup gap** (D2)
- **`origin=admin`** — staff created on behalf of cx and **force-processed** → serials reserve **before payment**
- **Completion is payment-gated** — order can't reach `completed` until cash is collected

---

### Data Flow

```
[Marcus calls — admin takes order by phone]
        │
        ├──→ orders INSERT (customer_id=20, source=phone, origin=admin, status=pending, payment_status=unpaid,
        │                   billing snapshot = Marcus Webb's address,
        │                   shipping snapshot = NULL — in-store pickup)
        ├──→ order_lines INSERT (2 lines: ECM-2024 + TCM-2024)
        ├──→ order_line_fees INSERT (Programming $40 + Gas Tuning $25 on ECM · Programming $40 on TCM)
        └──→ order_events INSERT (order_placed)
              │
              └──→ AvaTax QUOTE (uncommitted) on 5 taxable lines (2 units + 3 fees).
                   Returns tax_amount per line/fee from the API.
                   NOT committed yet — order is unpaid. Quote holds through `processing`.

[Admin FORCE-PROCESSES — serials assigned without payment (origin=admin)]
        │
        ├──→ order_lines UPDATE (line 48 ← SN-201, line 49 ← SN-301)
        ├──→ inventory_serials UPDATE (SN-201, SN-301: in_stock → reserved)        ◄── D2 — reserved while UNPAID
        ├──→ orders.status → processing
        └──→ order_events INSERT (processing, {"forced":true,"origin":"admin"})

[Units staged at counter]
        │
        └──→ order_events INSERT (ready_for_pickup)    ◄── D1: event only, status stays processing

[Marcus arrives, pays cash, takes both units]
        │
        ├──→ payments INSERT (cash, amount=525.01 EXACT, status=paid, received_at=14:00, received_by=1)  ◄── collect cash FIRST
        ├──→ orders.payment_status → paid
        ├──→ AvaTax COMMIT — quote promoted to committed sales invoice (commitInvoice)
        ├──→ inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-0020 ×2)
        ├──→ inventory_serials UPDATE (SN-201, SN-301: reserved → sold)
        ├──→ orders.status → completed, completed_at=14:00, completed_by=1, closed_at=14:00  ◄── only AFTER payment_status=paid
        └──→ order_events INSERT (payment_received, completed)
             activity_log (spatie) auto-records the model changes
             (no shipment row — no carrier)

[Cancel — no-show, goods NOT picked up (serials still reserved → cancelable)]
        │
        ├──→ orders.status → cancelled, closed_at set
        ├──→ inventory_serials UPDATE (SN-201, SN-301: reserved → in_stock, location stays Warehouse A)
        │        (NO inventory_movements row — unit never physically moved during reserve)
        ├──→ AvaTax — quote was never committed, nothing to void
        └──→ order_events INSERT (order_cancelled)
             activity_log (spatie) records the cancel
             (no payment row — order was unpaid)
```

> Payment fires **at the end** (pickup). The order is `unpaid` through processing and while staged ready.

---

### Tables & enums (allowed values)

Every sample row below uses only these values — nothing is inferred from examples.

| Table.column | Allowed values |
|---|---|
| `orders.status` | `pending` · `processing` · `completed` · `cancelled` |
| `orders.payment_status` | `unpaid` · `paid` |
| `orders.source` | `walk_in` · `phone` · … (extensible) |
| `orders.origin` | `online` · `admin` · `web_admin` |
| `inventory_serials.status` | `in_stock` · `reserved` · `sold` |
| `order_events.event` | `order_placed` · `processing` · `ready_for_pickup` · `payment_received` · `completed` · `order_cancelled` |
| `payments.method` | `cash` · … (extensible) |
| `payments.status` | `paid` |
| `order_notes.type` | `private` (staff-only) · `customer` (portal) |

> **Enums live in PHP, not MySQL.** Every allowed-value set above is a **PHP backed enum + model cast + FormRequest validation** — DB columns are plain `string`, **not** MySQL `ENUM`. Adding a value (another `source`, `payments.method`, etc.) is a PHP change, **no migration**.

---

### Schema + Data

**`customers`**
```
id   name         email              phone         status  tax_exempt
20   Marcus Webb  marcus@example.com 555-200-0002  active  false
```

**`customer_addresses`**
```
id  customer_id  type     line1              city     state  postal  is_default
30  20           billing  1420 Oak Park Dr   Houston  TX     77018   true
```

**`orders`**
```
id  number         customer_id  source  origin  status    payment_status  shipping  grand_total  created_by
20  ORD-2026-0020  20           phone   admin   completed paid            0.00      525.01       1

-- timestamps + completion (pickup → completed_*)
created_at           completed_at         completed_by  shipped_at  shipped_by  closed_at
2026-05-26 09:00:00  2026-05-26 14:00:00  1             NULL        NULL        2026-05-26 14:00:00

-- billing snapshot (CUSTOMER's address — known cx, phone order)
billing_first_name  billing_last_name  billing_email       billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Marcus              Webb               marcus@example.com  555-200-0002   1420 Oak Park Dr       Houston       TX             77018                US

-- shipping snapshot (NULL — in-store pickup)
shipping_first_name … all NULL
```

> **Addresses (simple):** billing snapshot = the customer's billing address; shipping snapshot = the shipping address — NULL here because it's in-store pickup (nothing to ship). AvaTax just receives the address and returns the tax. Nothing extra.

> **Completion fields:** pickup orders set `completed_at` / `completed_by` (handover at the counter). `created_at` and `closed_at` bracket the order's life for reporting (`closed_at` = completed **or** cancelled time). (`delivered_at` / `delivered_by` deferred — future delivery-confirmation feature.)

**`order_lines`**
```
id  order_id  product_listing_id  sku       product_name                 inventory_serial_id  unit_price  tax_amount  line_total
48  20        14                  ECM-2024  Engine Control Module        SN-201               200.00      16.50       216.50
49  20     15                  TCM-2024  Transmission Control Module  SN-301               180.00      14.85       194.85
```

> `line_total = unit_price + tax_amount`. Each line allocates one serial.

> **Line item (admin builder view — Woo/Shopify-style):** `product_listing` (line item) · `sku` (product) · `location` + `stock` (**readonly**, from inventory/serial) · `price` (`unit_price`) + `tax` (`tax_amount`) = **line subtotal** (`line_total`). **`price` + `tax` editable**; `location` + `stock` readonly. Here: ECM listing · SKU ECM-2024 · Warehouse A · SN-201 `in_stock` · $200.00 + $16.50 = $216.50.

**`order_line_fees`**
```
id  order_line_id  name             amount  tax_amount  fee_total  created_by  created_at
3   48             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
4   48             Gas Tuning Fee   25.00   2.06        27.06      1           2026-05-26 09:00:00
5   49             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
```

> `fee_total = amount + tax_amount`. Fees attach to whichever **line** they service — ECM (line 48) gets two, TCM (line 49) gets one.

> **Fee line (admin builder view):** `name` + `price` (`amount`) + `tax` (`tax_amount`) = **fee line total** (`fee_total`). **`price` + `tax` editable** (like Woo/Shopify).

> **Line-level tax:** both `order_lines` **and** `order_line_fees` carry their **own** `tax_amount` — AvaTax taxes each unit and each fee as a separate line (5 here). `line_total = unit_price + tax_amount`, `fee_total = amount + tax_amount`. There's no separate order-level tax column — order tax is the sum of these.

**Grand total math**
```
Line items:
  ECM-2024:   $200.00 unit + $16.50 tax = $216.50  ← line_total
  TCM-2024:   $180.00 unit + $14.85 tax = $194.85  ← line_total
Per-line fees:
  Programming (ECM):  $40.00 + $3.30 = $43.30  ← fee_total
  Gas Tuning  (ECM):  $25.00 + $2.06 = $27.06  ← fee_total
  Programming (TCM):  $40.00 + $3.30 = $43.30  ← fee_total
────────────────────────────────────────────────
Sum of line totals:                      $411.35
Sum of fee totals:                      + $113.66
Shipping:                               +   $0.00
────────────────────────────────────────────────
grand_total:                             $525.01
```

**`payments`**
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
23  20        order         20          payment  cash    525.01   paid    2026-05-26 14:00:00   1            1
```

> One row, full amount, collected **at pickup** (14:00) — hours after the order was placed (09:00).

> **Cash rule:** exact full amount only — **no partial payments**, one settling payment for the full `grand_total`, always **USD ($)**. No change-due / overpayment handling.

> **No shipment row** — in-store pickup, no carrier involved.

**`inventory_serials`**
```
serial   status  location  note
SN-201   sold    NULL      with Marcus Webb — ECM programmed + gas tuned, picked up
SN-301   sold    NULL      with Marcus Webb — TCM programmed, picked up
```

**`inventory_movements`**
```
id  serial   type     from         to           reference       notes
54  SN-201   receive  NULL         Warehouse A  PO-2026-012     initial stock receipt
55  SN-301   receive  NULL         Warehouse A  PO-2026-014     initial stock receipt
56  SN-201   sale     Warehouse A  NULL         ORD-2026-0020   handed to Marcus at counter (14:00)
57  SN-301   sale     Warehouse A  NULL         ORD-2026-0020   handed to Marcus at counter (14:00)
```

> `reserved` is a serial **status**, not a movement — no `inventory_movements` row fires at assignment. The only physical move is the `sale` at pickup (14:00). Inventory state mirrors physical reality, not accounting state.

> **Reservation lock:** when assigned, each serial is **locked to ORD-2026-0020** (`lockForUpdate` / atomic allocation) so it can never be reserved by another order while staged. The `sale` movement records **order number + customer name** for traceability (`ORD-2026-0020` · Marcus Webb).

---

### AvaTax Lifecycle (quote → commit)

> **All `tax_amount` values in this doc come from the AvaTax API — never hand-entered.** Two service calls, nothing more:

```
09:00  order placed (unpaid)   →  AvaTaxService::calculateTax()  →  SalesOrder (quote, uncommitted)
                                   returns tax_amount per line + fee; stored on the rows.
14:00  payment received        →  AvaTaxService::commitInvoice() →  SalesInvoice (committed)
                                   called from OrderService::recordCashPayment(), code = order number.
       — OR —
14:00  cancelled (no-show)     →  quote was never committed → nothing to commit, nothing to void.
```

> **Simple rule:** quote at placement (so totals display), commit only when cash is received. The AvaTax document code **is the order number** (`ORD-2026-0020`) — no extra column to store; any future void/adjust references the order number. Per-line fees ride along as AvaTax lines (`sku = FEE-<name>`). Tax is **rounded to 2 decimals per line** (e.g. $25 × 8.25% = $2.0625 → $2.06). (Cancel here is always pre-payment, so nothing is ever committed.)

---

### Order Events (append-only)

```
id  order_id  event             metadata                                                          created_by  created_at
1   20        order_placed      {"lines":2,"skus":["ECM-2024","TCM-2024"],"grand_total":"525.01"} 1           2026-05-26 09:00:00
2   20        processing        {"forced":true,"origin":"admin","serials":["SN-201","SN-301"]}    1           2026-05-26 09:05:00
3   20        ready_for_pickup  {"units":2}                                                       1           2026-05-26 12:00:00
4   20        payment_received  {"method":"cash","amount":"525.01"}                               1           2026-05-26 14:00:00
5   20        completed         {"pickup":true}                                                   1           2026-05-26 14:00:00

-- alternate terminal event if the customer never shows (instead of rows 4–5):
4'  20        order_cancelled   {"reason":"no_show","serials_released":["SN-201","SN-301"]}        1           2026-05-26 18:00:00
```

> `created_by = 1` → Admin John (counter staff). Every transition is admin-driven. Every operation — placed, by whom, when, payment received, ready, completed/cancelled — gets its own append-only row.

> **Three logs, no overlap:**
> - **`order_events`** (this table) — the **lifecycle timeline**: placed → processing → ready → paid → completed/cancelled. Customer-facing per-order story.
> - **`activity_log`** (existing **spatie/laravel-activitylog**) — the **system audit trail**: who-did-what attribute changes (`created`/`updated`/`deleted`), auth events, and custom events (`serials_generated`, etc.), with `causer` = the user.
> - **`order_notes`** — **human free-text** staff write (`private`/`customer`); see Order Notes section.
>
> `Order` uses `LogsActivity` (`logFillable()->logOnlyDirty()`). Note `status` / `payment_status` / `grand_total` are **not fillable** (set via `forceFill`), so activity_log doesn't track those transitions — that's exactly why `order_events` exists. No new audit table is needed; activity_log is already wired up.

**Rendered timeline:**
```
● Order placed (phone) — ECM-2024 + TCM-2024 · 2 units · $525.01
  2026-05-26  9:00 AM  ·  by Admin John
● Processing — serials SN-201, SN-301 assigned
  2026-05-26  9:05 AM  ·  by Admin John
● Ready for pickup — both units staged at counter
  2026-05-26  12:00 PM  ·  by Admin John
● Payment received — $525.01 via Cash
  ECM $216.50 · TCM $194.85 · programming ×2 $86.60 · gas tuning $27.06 · no shipping
  2026-05-26  2:00 PM  ·  by Admin John
● Order completed — in-store pickup, both units handed over
  2026-05-26  2:00 PM  ·  by Admin John
```

---

### Order Notes (admin-authored, free-text)

Human notes staff attach to the order — right-rail feed (WooCommerce/Shopify style). Not system events, not audit.

```
id  order_id  type      body                                  created_by  created_at           deleted_at
1   20        private   Cx called, confirmed pickup time.     1           2026-05-26 09:10:00  NULL
2   20        customer  Units programmed + ready for pickup.  1           2026-05-26 12:05:00  NULL
```

> Display: "May 26, 2026 at 9:10 am". `type` (PHP enum): `private` (staff-only) · `customer` (shown in portal; email-on-create = future).

> **Soft delete + permission matrix:** `deleted_at` nullable (Laravel `SoftDeletes`). Soft delete = author (own note) + admin. **Force delete (row gone) = admin only.** Gated by Spatie permission matrix.

---

### Timeline (canonical — one source of truth)

One row per moment. Status, payment, inventory, and events all read from here — change a time once, not in five places.

| time  | status      | payment_status | inventory (SN-201/SN-301) | order_events           |
|-------|-------------|----------------|---------------------------|------------------------|
| 09:00 | pending     | unpaid         | in_stock                  | order_placed           |
| 09:05 | processing  | unpaid         | reserved (locked)         | processing             |
| 12:00 | processing  | unpaid         | reserved (locked)         | ready_for_pickup       |
| 14:00 | completed   | paid           | sold                      | payment_received, completed |
| 18:00¹| cancelled   | unpaid         | in_stock (released)       | order_cancelled        |

> ¹ **Cancel fork** — staff cancels manually after a no-show; the 18:00 row *replaces* the 14:00 row. The two are mutually exclusive terminal outcomes.
>
> - **Status:** stays `processing` from 09:05 → 14:00. `ready_for_pickup` is an event, not a status (D1) — read it from the latest `order_events` row.
> - **payment_status:** truth lives in the `payments` row; `orders.payment_status` is a rollup. No-show = no payment row → stays `unpaid`; `status=cancelled` carries the cancellation.
> - **inventory:** `reserved` is locked to this order (D2). On cancel it goes back to `in_stock` with **no movement row** (the unit never physically left Warehouse A).
> - **force-process:** serials are `reserved` at **09:05 while `unpaid`** — the admin's process decision drives reservation, not payment (`origin=admin`). Cash is collected at pickup, and **`completed` fires only once `paid`**.

---

### Invariants (guardrails)

These must hold for every order built from this pattern:

- `grand_total = Σ line_totals + Σ fee_totals + shipping`
- cash `payment.amount` must equal `grand_total` **exactly** — no partial payments
- a serial is `reserved` by **at most one** order at a time (locked while staged)
- serials may be `reserved` **before** payment when `origin ∈ {admin, web_admin}` (force-process) — reservation is driven by the process decision, not payment
- **`status = completed` requires `payment_status = paid`** — cash is collected before the order is completed
- pickup is **all-or-nothing** — both units handed over together, or none
- **cancel = unpaid order only:** before goods leave (serials `reserved`, `payment_status = unpaid`) → release serials → `in_stock`, `status → cancelled`. Refunds (paid order) & anything after the goods leave are a **separate scenario** — not covered here

---

### Key Design Notes

Only the *decisions* — values visible in the sample data aren't repeated here.

| Rule | Value |
|------|-------|
| Readiness (D1) | **order_event `ready_for_pickup`** — status stays `processing`, no new status value |
| Serial reservation (D2) | `in_stock → reserved → sold`; no movement row at reserve |
| Origin / force-process | **`origin=admin`** — staff force-processes → serials reserve **before payment**; reservation trigger = process decision, not `payment_status` |
| Completion gate | **`completed` requires `paid`** — cash collected at pickup before the order completes |
| Serial locking | reserved serial **locked to one order**, never assignable elsewhere |
| Tax source | **All `tax_amount` from AvaTax API** — never hand-entered; **rounded to 2 dp per line** |
| AvaTax lifecycle | `calculateTax()` quote at placement → `commitInvoice()` on payment; doc code = order number; cancel-before-pay = nothing to commit/void |
| Payment timing | **at pickup**, not at placement |
| Cash rule | exact full amount, **no partial**, single payment, **USD only**, no change handling |
| Cancel | **unpaid order only** — no-show before pickup (serials `reserved`) → release serials → `in_stock`, `cancelled`. Refunds & anything after pickup = separate scenario |
| Timestamps | `completed_at`/`completed_by` (completion); `created_at`/`closed_at` bracket the order. `delivered_at`/`delivered_by` deferred (future) |
| Logging | `order_events` (lifecycle timeline) **+** spatie `activity_log` (CRUD/auth audit, causer) |
| Order notes | human free-text on order (`private`/`customer`); soft delete = author+admin, **force delete = admin only**; ≠ events ≠ audit |
| Order reference | internal FKs use order **id** (`20`); human/external refs use order **number** (`ORD-2026-0020`) — inventory movements, AvaTax doc code |
| Order-level fees | **Not used here** (all fees per-line). Future `order_fees` table for non-line fees (handling/expedite/surcharge) |
