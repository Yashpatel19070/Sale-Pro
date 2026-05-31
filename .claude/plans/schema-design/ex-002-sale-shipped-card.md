# ex-002 — ORD-2026-0021 — Admin Force-Process, Shipped to Customer, Card (Stripe) Paid Later

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

**Scenario:** Marcus Webb calls the Houston shop; **staff creates the order from the admin side on his behalf** (`origin=admin`). The admin **force-processes** it immediately — two serials (SN-203, SN-303) are **assigned + reserved with no payment yet**. Later Marcus pays **$545.01 by card** via a **Stripe checkout link**. Once paid, the order ships to his address and is **completed on ship** (delivery confirmation is a future feature).

**Core idea:** serial assignment is triggered by the **admin's decision to process**, *not* by payment — but the order **still won't ship until it's paid**.

> _Example only — the goal is to understand the data flow. IDs, order numbers, and timestamps are illustrative._

**Key aspects:**
- **`origin=admin`** (staff created on behalf of customer) — vs `online` (customer self-service)
- **Force process** → serials `reserved` **before payment**
- **`source=phone`** (customer called in), shipped to **customer's shipping address**
- Payment = **card via Stripe link**, paid **after** processing, **before** shipping
- `shipped` is an **order_event**; status `processing → completed` once **paid + shipped** (delivery confirmation = future)
- Sale inventory movement fires at **ship time**
- **Shipping cost tracking** — `shipments.label_cost` records what we pay the carrier (admin-only, not on receipt), separate from the customer's shipping charge

---

### Origin rule (the new concept)

| | Serial reservation trigger | Shipping gate |
|---|---|---|
| `origin = online` | after **payment** (pay-first) | `payment_status = paid` ✓ |
| `origin = admin` / `web_admin` | at **admin force-process** (no payment needed) | `payment_status = paid` ✓ |

- **Reservation** is decoupled from payment **only** for `admin` / `web_admin`.
- **Shipping** always requires `payment_status = paid`, regardless of origin.
- `reserved` holds the unit the whole time; → `sold` only when it physically ships.
- `source` = sales channel (phone/walk_in/web); `origin` = creation context (online self-serve vs staff admin side). They are independent — here `source=phone`, `origin=admin`.

---

### Data Flow

```
[Staff creates order on admin side, on behalf of Marcus]
        │
        ├──→ orders INSERT (customer_id=20, source=phone, origin=admin,
        │                   status=pending, payment_status=unpaid,
        │                   billing snapshot = billing address, shipping snapshot = shipping address)
        ├──→ order_lines INSERT (2 lines: ECM-2024 + TCM-2024)
        ├──→ order_line_fees INSERT (Programming $40 + Gas Tuning $25 on ECM · Programming $40 on TCM)
        ├──→ orders.shipping = 20.00 (shipping charged to customer)
        └──→ order_events INSERT (order_placed)
              └──→ AvaTax QUOTE (uncommitted), ship-to = shipping address. Returns tax_amount per line/fee.

[Admin FORCE-PROCESSES — no payment required (origin=admin)]
        │
        ├──→ order_lines UPDATE (line 52 ← SN-203, line 53 ← SN-303)
        ├──→ inventory_serials UPDATE (SN-203, SN-303: in_stock → reserved, locked to ORD-2026-0021)
        ├──→ orders.status → processing            ◄── reserved while still UNPAID
        └──→ order_events INSERT (processing, {"forced":true,"origin":"admin"})   [+ activity_log]

[Marcus pays the Stripe checkout link by card]
        │
        ├──→ payments INSERT (card, amount=545.01, status=paid, received_at=11:00, received_by=1)
        ├──→ orders.payment_status → paid
        ├──→ AvaTax COMMIT — SalesInvoice, code = order number
        └──→ order_events INSERT (payment_received)   [+ activity_log]

[Staff buys a shipping label + ships — paid, so allowed; order COMPLETES on ship]
        │
        ├──→ shipments INSERT (shippable=order/21, direction=outbound, carrier=UPS, tracking=1Z…, label_cost=12.40, status=shipped, shipped_at=13:00)
        ├──→ orders.shipped_at=13:00, shipped_by=1
        ├──→ inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-0021 ×2)
        ├──→ inventory_serials UPDATE (SN-203, SN-303: reserved → sold, location → NULL/in-transit)
        ├──→ orders.status → completed, completed_at=13:00, completed_by=1, closed_at=13:00
        │    (delivered_at / delivered_by deferred — future delivery-confirmation feature)
        └──→ order_events INSERT (shipped, completed)   [+ activity_log]

[Cancel — unpaid order only, BEFORE ship (serials still reserved)]
        │
        ├──→ inventory_serials UPDATE (SN-203, SN-303: reserved → in_stock)
        ├──→ orders.status → cancelled, closed_at set
        ├──→ AvaTax — quote never committed → nothing to void
        └──→ order_events INSERT (order_cancelled)   [+ activity_log]

        Refunds (cancelling a PAID order) and anything after the goods leave are a separate scenario — not covered here.
```

---

### Tables & enums (allowed values)

| Table.column | Allowed values |
|---|---|
| `orders.status` | `pending` · `processing` · `completed` · `cancelled` |
| `orders.payment_status` | `unpaid` · `paid` |
| `orders.source` | `walk_in` · `phone` · `web` · … (extensible) |
| `orders.origin` | `online` · `admin` · `web_admin` |
| `inventory_serials.status` | `in_stock` · `reserved` · `sold` |
| `order_events.event` | `order_placed` · `processing` · `payment_received` · `shipped` · `completed` · `order_cancelled` |
| `payments.method` | `cash` · `card` · … (extensible) |
| `payments.status` | `paid` |
| `shipments.status` | `pending` · `shipped` · … (`in_transit`/`delivered` future) |
| `shipments.direction` | `outbound` (to customer) |
| `shipments.shippable_type` | `order` · `complaint` · `replacement` |
| `order_notes.type` | `private` (staff-only) · `customer` (portal) |

> Both pickup and shipped orders complete via the **`completed`** event → `status = completed`. (Carrier delivery confirmation — a `delivered` event + `delivered_at`/`delivered_by` — is a future feature.)

> **Enums live in PHP, not MySQL.** Every allowed-value set above is a **PHP backed enum + model cast + FormRequest validation** — DB columns are plain `string`, **not** MySQL `ENUM`. Adding a value (e.g. `shipments.direction = inbound` for returns later) is a PHP change, **no migration**.

---

### Schema + Data

**`customers`**
```
id   name         email              phone         status  tax_exempt
20   Marcus Webb  marcus@example.com 555-200-0002  active  false
```

**`customer_addresses`**
```
id  customer_id  type      line1               city     state  postal  is_default
30  20           billing   1420 Oak Park Dr    Houston  TX     77018   true
31  20           shipping  9001 Westheimer Rd  Houston  TX     77063   false
```
> Shipping address kept in Houston → same 8.25% rate.

**`orders`** (key fields)
```
id  number         customer_id  source  origin  status     payment_status  shipping  grand_total  created_by
21  ORD-2026-0021  20           phone   admin   completed  paid            20.00     545.01       1

-- timestamps + fulfillment (completes on ship)
created_at           shipped_at           shipped_by  completed_at         completed_by  closed_at
2026-05-27 09:00:00  2026-05-27 13:00:00  1           2026-05-27 13:00:00  1             2026-05-27 13:00:00

-- billing snapshot (customer's billing address)
billing_first_name  billing_last_name  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Marcus              Webb               1420 Oak Park Dr       Houston       TX             77018                US

-- shipping snapshot (customer's shipping address — populated)
shipping_first_name  shipping_last_name  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Marcus               Webb                9001 Westheimer Rd      Houston       TX             77063                US
```
> Order completes at ship → `completed_at`/`completed_by` set. `delivered_at`/`delivered_by` columns **deferred (commented out of schema)** — future delivery-confirmation feature.

**`order_lines`**
```
id  order_id  product_listing_id  sku       product_name                 inventory_serial_id  unit_price  tax_amount  line_total
52  21        14                  ECM-2024  Engine Control Module        SN-203               200.00      16.50       216.50
53  21     15                  TCM-2024  Transmission Control Module  SN-303               180.00      14.85       194.85
```

> **Line item (admin builder view — Woo/Shopify-style):** `product_listing` (line item) · `sku` (product) · `location` + `stock` (**readonly**, from inventory/serial) · `price` (`unit_price`) + `tax` (`tax_amount`) = **line subtotal** (`line_total`). **`price` + `tax` editable**; `location` + `stock` readonly. Here: ECM listing · SKU ECM-2024 · Warehouse A · SN-203 `in_stock` · $200.00 + $16.50 = $216.50.

**`order_line_fees`**
```
id  order_line_id  name             amount  tax_amount  fee_total  created_by  created_at
9   52             Programming Fee  40.00   3.30        43.30      1           2026-05-27 09:00:00
10  52             Gas Tuning Fee   25.00   2.06        27.06      1           2026-05-27 09:00:00
11  53             Programming Fee  40.00   3.30        43.30      1           2026-05-27 09:00:00
```

> **Fee line (admin builder view):** `name` + `price` (`amount`) + `tax` (`tax_amount`) = **fee line total** (`fee_total`). **`price` + `tax` editable** (like Woo/Shopify).

> **Line-level tax:** every taxable thing carries its **own** `tax_amount` — each product line (`order_lines.tax_amount`) **and** each fee (`order_line_fees.tax_amount`). AvaTax taxes all 5 as separate lines (2 units + 3 fees); `line_total = unit_price + tax_amount`, `fee_total = amount + tax_amount`. There's no separate order-level tax column — order tax is the sum of these.

**Grand total math**
```
Sum of line totals:    $411.35   (ECM 216.50 + TCM 194.85)
Sum of fee totals:   + $113.66   (43.30 + 27.06 + 43.30)
Shipping (flat):     +  $20.00   (charged to customer, not taxed here)
────────────────────────────────
grand_total:           $545.01
```

**`payments`**
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
25  21        order         21          payment  card    545.01   paid    2026-05-27 11:00:00   1            1
```
> Card via Stripe link, paid **after** force-process, **before** ship. `received_at` / `received_by` capture when/who recorded the payment. Stripe identifiers (payment_intent, charge id) are added later when card integration is built.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  service  tracking_number  label_reference  label_cost  status   shipped_at           created_by
1   order           21            outbound   UPS      Ground   1Z999AA10123456  lbl_9921         12.40       shipped  2026-05-27 13:00:00  1
```

> `shipments.delivered_at` deferred (commented out) — future delivery-confirmation feature. Shipment stays `status=shipped`.

> **Shipping charge vs label cost — two different numbers:**
> - `orders.shipping` = what the **customer is charged** (revenue), shown on the receipt — here $20.00.
> - `shipments.label_cost` = what **we pay the carrier** for the label (our expense), **admin-only, never on the customer receipt** — here $12.40.
> - **Margin** = `orders.shipping − Σ label_cost` (20.00 − 12.40 = **$7.60**). `label_cost` is **not** part of `grand_total`.
> - Buying a label = an **outbound** shipment (`direction=outbound`), linked via `shippable_type`/`shippable_id` → lets us total shipping spend **per order** and **per year**.
> - **Free shipping:** even when `orders.shipping = 0` (customer pays nothing), `label_cost` is **still recorded** — we still pay the carrier, so the spend is tracked.
> - **Receipt rule:** receipt shows **`orders.shipping` only** — paid → `$20.00` · free → `$0.00`/"Free". **Never show `label_cost`** (internal/admin). Same rule both cases; only the `orders.shipping` value differs.

**`inventory_serials`**
```
serial   status  location  note
SN-203   sold    NULL      shipped to Marcus Webb (UPS 1Z…) — ORD-2026-0021
SN-303   sold    NULL      shipped to Marcus Webb (UPS 1Z…) — ORD-2026-0021
```

**`inventory_movements`** — `sale` fires at **ship** (13:00), not at force-process or delivery
```
id  serial   type     from         to           reference       notes
62  SN-203   receive  NULL         Warehouse A  PO-2026-016     initial stock receipt
63  SN-303   receive  NULL         Warehouse A  PO-2026-017     initial stock receipt
64  SN-203   sale     Warehouse A  NULL         ORD-2026-0021   shipped to Marcus Webb — left warehouse 13:00
65  SN-303   sale     Warehouse A  NULL         ORD-2026-0021   shipped to Marcus Webb — left warehouse 13:00
```
> `reserved` (at force-process, 09:05) is a **status only** — no movement row. The only physical move is the `sale` at ship time.

---

### AvaTax Lifecycle (quote → commit)

> All `tax_amount` from the AvaTax API — never hand-entered.

```
09:00  order created (unpaid)  →  calculateTax()   →  SalesOrder (quote, uncommitted), ship-to = shipping address
09:05  force-processed         →  quote unchanged (still uncommitted — no payment)
11:00  payment received        →  commitInvoice()  →  SalesInvoice (committed), code = order number
       — OR —
cancel before payment          →  quote never committed → nothing to void
```
> Force-process does **not** touch AvaTax — commit still happens only on payment. Tax rounded to 2 dp per line.

---

### Order Events (append-only)

```
id  order_id  event             metadata                                                       created_by  created_at
1   21        order_placed      {"source":"phone","origin":"admin","grand_total":"545.01"}     1           2026-05-27 09:00:00
2   21        processing        {"forced":true,"origin":"admin","serials":["SN-203","SN-303"]} 1           2026-05-27 09:05:00
3   21        payment_received  {"method":"card","amount":"545.01"}                             1           2026-05-27 11:00:00
4   21        shipped           {"carrier":"UPS","tracking":"1Z999AA10123456"}                 1           2026-05-27 13:00:00
5   21        completed         {"shipped":true}                                                1           2026-05-27 13:00:00

-- cancel terminal (replaces rows from the cancel point on):
–   21        order_cancelled   {"reason":"cancelled_before_ship"}                              1           …
```
> The `processing` row records `forced:true` so the audit trail shows the unpaid force-process.

> **Three logs, no overlap:** `order_notes` = human free-text (private/customer); `order_events` = lifecycle timeline; spatie **`activity_log`** = system audit (who-did-what attribute changes, auth, custom events, `causer` = user). `Order` status/payment_status aren't fillable (set via `forceFill`), so `order_events` carries the transitions and `activity_log` carries the rest.

**Rendered timeline:**
```
● Order placed (phone, admin) — ECM-2024 + TCM-2024 · 2 units · $545.01
  2026-05-27  9:00 AM  ·  by Admin John
● Processing — serials SN-203, SN-303 assigned (force-process, unpaid)
  2026-05-27  9:05 AM  ·  by Admin John
● Payment received — $545.01 via Card (Stripe)
  ECM $216.50 · TCM $194.85 · programming ×2 $86.60 · gas tuning $27.06 · shipping $20.00
  2026-05-27  11:00 AM  ·  by Admin John
● Shipped — UPS · tracking 1Z999AA10123456
  2026-05-27  1:00 PM  ·  by Admin John
● Order completed — shipped to customer (delivery confirmation: future)
  2026-05-27  1:00 PM  ·  by Admin John
```

---

### Order Notes (admin-authored, free-text)

Human notes staff attach to the order — right-rail feed (WooCommerce/Shopify style). Not system events, not audit.

```
id  order_id  type      body                                            created_by  created_at           deleted_at
1   21        private   Force-processed before payment per cx request.  1           2026-05-27 09:05:00  NULL
2   21        customer  Shipped via UPS, tracking 1Z999AA10123456.      1           2026-05-27 13:00:00  NULL
```

> Display: "May 27, 2026 at 9:05 am". `type` (PHP enum): `private` (staff-only) · `customer` (shown in portal; email-on-create = future).

> **Soft delete + permission matrix:** `deleted_at` nullable (Laravel `SoftDeletes`). Soft delete = author (own note) + admin. **Force delete (row gone) = admin only.** Gated by Spatie permission matrix.

---

### Canonical Timeline (one source of truth)

| time        | status     | payment_status | inventory (SN-203/SN-303) | order_events     |
|-------------|------------|----------------|---------------------------|------------------|
| 05-27 09:00 | pending    | unpaid         | in_stock                  | order_placed     |
| 05-27 09:05 | processing | **unpaid**     | **reserved (locked)**     | processing *(forced)* |
| 05-27 11:00 | processing | paid           | reserved                  | payment_received |
| 05-27 13:00 | completed  | paid           | sold (left warehouse)     | shipped, completed |
| cancel¹     | cancelled  | unpaid         | in_stock (released)       | order_cancelled  |

> ¹ Cancel applies to an **unpaid** order before ship → release serials → `in_stock`, `cancelled`. Cancelling a paid order (refund) and anything after ship are a **separate scenario**.
>
> The standout row is **09:05**: serials are `reserved` while `payment_status = unpaid` — the force-process behavior. An `online` order would instead reserve only after the 11:00 paid row.

---

### Invariants (guardrails)

- `grand_total = Σ line_totals + Σ fee_totals + shipping`
- card `payment.amount` must equal `grand_total` exactly
- **nothing ships until `payment_status = paid`** — all origins
- serials may be `reserved` **before** payment when `origin ∈ {admin, web_admin}` (force-process); `online` reserves only after paid
- a serial is `reserved` by **at most one** order at a time (locked while staged)
- **`status = completed` requires `payment_status = paid`** (and shipped); delivery confirmation (`delivered_at`/`delivered_by`) deferred from schema — future feature
- serial `reserved` → `sold` at **ship time**; cancel-before-ship → back to `in_stock`
- **cancel = unpaid order only** (before ship, serials `reserved`); refunds (paid order) and anything after ship are a **separate scenario**

---

### Key Design Notes

| Rule | Value |
|------|-------|
| Origin | **`admin`** (staff created on behalf of cx); gates the force-process behavior |
| Force process | admin reserves serials **without payment**; reservation trigger = process decision, not `payment_status` |
| Reservation (D2) | `in_stock → reserved → sold`; reserved at force-process (09:05), no movement row |
| Shipping gate | **always** requires `payment_status = paid`, regardless of origin |
| Payment | card via **Stripe checkout link**; `received_at`/`received_by` recorded; Stripe ids (payment_intent, charge) deferred. Paid after process, before ship |
| AvaTax lifecycle | `calculateTax()` quote at create → `commitInvoice()` on payment; force-process doesn't touch AvaTax; doc code = order number |
| Tax source | all `tax_amount` from AvaTax API; rounded to 2 dp per line |
| Shipping charge (customer) | `orders.shipping` — what the customer pays (revenue), on receipt; flat $20, not taxed here |
| Label cost (ours) | `shipments.label_cost` — what we pay the carrier (expense), **admin-only, not on receipt**; tracked even on **free shipping** (`orders.shipping=0`) |
| Outbound label | buying a label = `direction=outbound` shipment linked via `shippable_type`/`shippable_id` → shipping spend per order / per year |
| Completion | order completes once **paid + shipped** → `completed_at`/`completed_by` set; `delivered_at`/`delivered_by` deferred from schema (delivery confirmation = future) |
| Cancel | **unpaid order only**, before ship (serials `reserved`) → release serials → `in_stock`, `cancelled`. Refunds & anything after ship = separate scenario |
| Logging | `order_events` (lifecycle) **+** spatie `activity_log` (CRUD/auth audit, causer) |
| Order notes | human free-text on order (`private`/`customer`); soft delete = author+admin, **force delete = admin only**; ≠ events ≠ audit |
| Order reference | internal FKs use order **id** (`21`); human/external refs use order **number** (`ORD-2026-0021`) |
| Order-level fees | not used (all fees per-line); future `order_fees` table for non-line fees |
