> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this file.

## Order Events — Scenarios

All scenarios use table `order_events`. Append-only — no `updated_at`. `created_by` NULL = system / webhook.

### Table: `order_events`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id, cascade delete |
| event | string(50) | No | — | Cast to `OrderEvent` enum |
| metadata | json | Yes | null | Event-specific structured data |
| created_by | foreignId | Yes | null | FK → users.id — NULL = system / webhook |
| created_at | timestamp | No | — | Auto — **no `updated_at`** |

> Append-only — no updates, no deletes. Human-readable label computed in PHP from `event + metadata` — no `description` column. Every service method that changes order state MUST dispatch an event row in the same `DB::transaction`. Migration uses `$table->timestamp('created_at')->useCurrent()` only — **not** `$table->timestamps()`.

### Indexes
- `order_id` — foreign key index (auto)
- `(order_id, created_at)` — composite for ordered timeline query (primary use case)
- `event` — filter by event type for reports (e.g. all shipments today)
- `created_by` — foreign key + staff activity reports

---

### Scenario A — Normal order · walk_in · cash · carrier delivery

**Flow:** `order_placed → payment_received → shipped → delivered`

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ──────────
order_placed      {"sku":"PROD-B","product_name":"Widget Max","grand_total":"245.00"}  admin
payment_received  {"method":"cash","amount":"245.00",                             admin
                   "subtotal":"200.00","fees":"20.00","shipping":"25.00"}
shipped           {"carrier":"FedEx","tracking":"FX-001","label_cost":"9.00",     warehouse
                   "address":"321 Oak Lane, Dallas TX 75201","shipment_id":1}
delivered         {"address":"321 Oak Lane, Dallas TX 75201","shipment_id":1}     admin
```

```
● Order placed — Widget Max · PROD-B · $245.00
  May 1  10:00 AM  ·  by Admin John

● Payment received — $245.00 via Cash
  subtotal $200.00 · service fee $20.00 · shipping $25.00
  May 1  10:00 AM  ·  by Admin John

● Shipped — FedEx · FX-001 · label $9.00
  to: 321 Oak Lane, Dallas TX 75201
  May 2  9:00 AM  ·  by Warehouse Sam

● Delivered
  321 Oak Lane, Dallas TX 75201
  May 4  1:00 PM  ·  by Admin John
```

---

### Scenario B — Normal order · walk_in · stripe_terminal · in-store pickup

**Flow:** `order_placed → payment_received → completed`

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ──────────
order_placed      {"sku":"PROD-A","product_name":"Widget Pro","grand_total":"180.00"}  admin
payment_received  {"method":"stripe_terminal","amount":"180.00",                  admin
                   "subtotal":"180.00","fees":"0.00","shipping":"0.00"}
completed         {}                                                              admin
```

```
● Order placed — Widget Pro · PROD-A · $180.00
  May 1  11:00 AM  ·  by Admin John

● Payment received — $180.00 via Stripe Terminal
  subtotal $180.00 · no fees · no shipping
  May 1  11:00 AM  ·  by Admin John

● Order completed — in-store pickup
  May 1  11:00 AM  ·  by Admin John
```

---

### Scenario C — Carrier + RTS + re-ship

**Source:** `online` — customer self-service via portal
**Flow:** `order_placed → payment_received → shipped → rts_triggered → re_shipped → delivered`

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ─────────────────────
order_placed      {"sku":"PROD-A","product_name":"Widget Pro","grand_total":"240.00"}  customer (user_id=20)
payment_received  {"method":"stripe_card","amount":"240.00",                      customer (user_id=20)
                   "subtotal":"200.00","fees":"20.00","shipping":"20.00"}
shipped           {"carrier":"FedEx","tracking":"FX-001","label_cost":"8.50",     warehouse
                   "address":"456 Oak St, Chicago IL 60601","shipment_id":1}
rts_triggered     {"carrier":"FedEx","tracking":"FX-001","shipment_id":1}         admin
re_shipped        {"carrier":"FedEx","tracking":"FX-002","label_cost":"8.50",     warehouse
                   "address":"789 Pine Ave Suite 100, Chicago IL 60602",
                   "address_id":15,"shipment_id":2}
delivered         {"address":"789 Pine Ave Suite 100, Chicago IL 60602",          admin
                   "shipment_id":2}
```

```
● Order placed — Widget Pro · PROD-A · $240.00
  May 13  9:00 AM  ·  by Customer (self-service)

● Payment received — $240.00 via Stripe Card
  subtotal $200.00 · service fee $20.00 · shipping $20.00
  May 13  9:00 AM  ·  by Customer

● Shipped — FedEx · FX-001 · label $8.50
  to: 456 Oak St, Chicago IL 60601  (home)
  May 20  10:00 AM  ·  by Warehouse Sam

● Return to sender — FedEx · FX-001
  package returned to warehouse
  May 24  2:00 PM  ·  by Admin John

● Re-shipped — FedEx · FX-002 · label $8.50
  to: 789 Pine Ave Suite 100, Chicago IL 60602  (work)
  May 27  9:00 AM  ·  by Warehouse Sam

● Delivered
  789 Pine Ave Suite 100, Chicago IL 60602
  May 29  2:00 PM  ·  by Admin John
```

---

### Scenario D — Stripe Checkout · async payment · carrier delivery

**Flow:** `order_placed → payment_pending → payment_confirmed → shipped → delivered`

```
event              metadata                                                       created_by
─────────────────  ─────────────────────────────────────────────────────────────  ──────────
order_placed       {"sku":"PROD-A","product_name":"Widget Pro","grand_total":"220.00"} admin
payment_pending    {"method":"stripe_checkout","amount":"220.00",                 admin
                    "session_id":"cs_live_xxx"}
payment_confirmed  {"method":"stripe_checkout","session_id":"cs_live_xxx"}        NULL (webhook)
shipped            {"carrier":"UPS","tracking":"1Z999AA1...","label_cost":"8.00", warehouse
                    "address":"88 Pine St, Denver CO 80201","shipment_id":3}
delivered          {"address":"88 Pine St, Denver CO 80201","shipment_id":3}      admin
```

```
● Order placed — Widget Pro · PROD-A · $220.00
  May 5  2:00 PM  ·  by Admin John

● Payment link sent — $220.00 via Stripe Checkout
  awaiting customer payment
  May 5  2:05 PM  ·  by Admin John

● Payment confirmed — $220.00 via Stripe Checkout
  session cs_live_xxx
  May 5  3:30 PM  ·  system (webhook)

● Shipped — UPS · 1Z999AA1... · label $8.00
  to: 88 Pine St, Denver CO 80201
  May 6  9:00 AM  ·  by Warehouse Sam

● Delivered
  88 Pine St, Denver CO 80201
  May 8  1:00 PM  ·  by Admin John
```

---

### Scenario E — Cheque payment · carrier delivery

**Flow:** `order_placed → payment_pending → payment_confirmed → shipped → delivered`

```
event              metadata                                                       created_by
─────────────────  ─────────────────────────────────────────────────────────────  ──────────
order_placed       {"sku":"PROD-B","product_name":"Widget Max","grand_total":"300.00"} admin
payment_pending    {"method":"cheque","amount":"300.00",                          admin
                    "cheque_number":"1042","cheque_date":"2026-05-05"}
payment_confirmed  {"method":"cheque","cheque_number":"1042"}                     admin
shipped            {"carrier":"FedEx","tracking":"FX-003","label_cost":"9.00",    warehouse
                    "address":"55 Elm Ave, Austin TX 78701","shipment_id":4}
delivered          {"address":"55 Elm Ave, Austin TX 78701","shipment_id":4}      admin
```

```
● Order placed — Widget Max · PROD-B · $300.00
  May 5  10:00 AM  ·  by Admin John

● Cheque received — $300.00 · #1042 · dated May 5
  awaiting clearance
  May 5  10:00 AM  ·  by Admin John

● Payment confirmed — cheque #1042 cleared
  May 9  11:00 AM  ·  by Admin John

● Shipped — FedEx · FX-003 · label $9.00
  to: 55 Elm Ave, Austin TX 78701
  May 10  9:00 AM  ·  by Warehouse Sam

● Delivered
  55 Elm Ave, Austin TX 78701
  May 12  2:00 PM  ·  by Admin John
```

---

### Scenario E2 — Stripe Checkout · expired · cancelled

**Flow:** `order_placed → payment_pending → payment_expired → cancelled`

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ──────────────
order_placed      {"sku":"PROD-A","product_name":"Widget Pro","grand_total":"240.00"} admin
payment_pending   {"method":"stripe_checkout","amount":"240.00",                  admin
                   "session_id":"cs_live_abc789xyz"}
payment_expired   {"method":"stripe_checkout","session_id":"cs_live_abc789xyz",   NULL (webhook)
                   "amount":"240.00"}
cancelled         {"reason":"Stripe Checkout session expired — no payment",       admin
                   "was_status":"pending"}
```

```
● Order placed — Widget Pro · PROD-A · $240.00
  May 5  2:00 PM  ·  by Admin John

● Payment link sent — $240.00 via Stripe Checkout
  awaiting customer payment
  May 5  2:05 PM  ·  by Admin John

● Payment link expired — $240.00 via Stripe Checkout
  session cs_live_abc789xyz · customer never paid
  May 6  2:05 PM  ·  system (webhook)

● Order cancelled
  Stripe Checkout session expired — no payment · was: pending
  May 6  3:00 PM  ·  by Admin John
```

> Stripe Checkout sessions expire after 24 hours by default. Admin reviews expired sessions and cancels manually.

---

### Scenario F — Back order flows (see Ex 15–18 for full data)

| Example | Source | Payment timing | Event sequence |
|---------|--------|---------------|----------------|
| Ex 15 ORD-017 | walk_in | prepaid cash | `back_order_created → payment_received → serial_assigned → shipped → delivered` |
| Ex 16 ORD-018 | walk_in | pay at pickup | `back_order_created → serial_assigned → payment_received → completed` |
| Ex 17 ORD-019 | phone | stripe_checkout prepaid | `back_order_created → payment_pending → payment_confirmed → serial_assigned → shipped → delivered` |
| Ex 18 ORD-020 | phone | pay when stock arrives | `back_order_created → serial_assigned → payment_received → shipped → delivered` |

> `back_order_created` always fires at order creation when `inventory_serial_id = NULL`. `serial_assigned` fires when stock arrives and admin assigns serial. `processing` state has no dedicated event — it is the transition point between `serial_assigned` + `payment_received/confirmed` both being present.

---

### Scenario G — Complaint + replacement

**Flow:** (after delivered) `→ complaint_opened → replacement_issued`

```
event               metadata                                                     created_by
──────────────────  ───────────────────────────────────────────────────────────  ──────────
complaint_opened    {"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151", admin
                     "sku":"PROD-A","type":"defective_on_arrival"}
replacement_issued  {"replacement_id":2,"number":"REP-2026-002","type":"free",   admin
                     "old_serial":"SN-151","new_serial":"SN-199"}
```

```
● Complaint opened — CMP-2026-003
  SN-151 · Widget Pro · defective on arrival
  May 6  11:00 AM  ·  by Admin Sarah

● Replacement issued — REP-2026-002  (free)
  SN-151 → SN-199 · internal fault confirmed
  May 9  2:00 PM  ·  by Admin John
```

> Scenario G is the short overview. Scenarios G2–G6 below show the complete event sequences for each complaint resolution path.

---

### Scenario G2 — Complaint · return + replacement (full sequence)

**Flow:** `complaint_opened → return_label_sent → unit_received → unit_examined → replacement_issued → replacement_shipped → replacement_delivered → complaint_closed`

```
event                  metadata                                                       created_by
─────────────────────  ─────────────────────────────────────────────────────────────  ──────────
complaint_opened       {"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151",   admin
                        "sku":"PROD-A","type":"defective_on_arrival"}
return_label_sent      {"complaint_id":3,"number":"CMP-2026-003","carrier":"FedEx",   admin
                        "label_cost":"8.50","shipment_id":50}
unit_received          {"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151"}   warehouse
unit_examined          {"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151",   admin
                        "result":"internal_issues"}
replacement_issued     {"replacement_id":2,"number":"REP-2026-002","type":"free",     admin
                        "old_serial":"SN-151","new_serial":"SN-199"}
replacement_shipped    {"replacement_id":2,"number":"REP-2026-002","carrier":"FedEx", warehouse
                        "tracking":"FX-010","label_cost":"9.00",
                        "address":"321 Oak Lane, Dallas TX 75201","shipment_id":51}
replacement_delivered  {"replacement_id":2,"number":"REP-2026-002",                   admin
                        "address":"321 Oak Lane, Dallas TX 75201"}
complaint_closed       {"complaint_id":3,"number":"CMP-2026-003",                     admin
                        "resolution":"replacement"}
```

```
● Complaint opened — CMP-2026-003
  SN-151 · Widget Pro · defective on arrival
  May 6  11:00 AM  ·  by Admin Sarah

● Return label sent — FedEx · label $8.50
  CMP-2026-003 · shipment #50
  May 6  11:30 AM  ·  by Admin Sarah

● Unit received — SN-151
  CMP-2026-003
  May 9  10:00 AM  ·  by Warehouse Sam

● Unit examined — internal issues
  CMP-2026-003 · SN-151 · warranty applies
  May 9  1:00 PM  ·  by Admin Sarah

● Replacement issued — REP-2026-002  (free)
  SN-151 → SN-199 · internal fault confirmed
  May 9  2:00 PM  ·  by Admin John

● Replacement shipped — FedEx · FX-010 · label $9.00
  to: 321 Oak Lane, Dallas TX 75201
  May 10  9:00 AM  ·  by Warehouse Sam

● Replacement delivered
  321 Oak Lane, Dallas TX 75201
  May 12  2:00 PM  ·  by Admin John

● Complaint closed — replacement
  CMP-2026-003
  May 12  2:05 PM  ·  by Admin John
```

---

### Scenario G3 — Complaint · in-store handoff + replacement

**Flow:** `complaint_opened → unit_received → unit_examined → replacement_issued → replacement_delivered → complaint_closed`

Customer brings unit to store — no return shipment needed. Replacement handed across the counter — no outbound shipment needed.

```
event                  metadata                                                       created_by
─────────────────────  ─────────────────────────────────────────────────────────────  ──────────
complaint_opened       {"complaint_id":4,"number":"CMP-2026-004","serial":"SN-152",   admin
                        "sku":"PROD-B","type":"not_working"}
unit_received          {"complaint_id":4,"number":"CMP-2026-004","serial":"SN-152"}   warehouse
unit_examined          {"complaint_id":4,"number":"CMP-2026-004","serial":"SN-152",   admin
                        "result":"internal_issues"}
replacement_issued     {"replacement_id":3,"number":"REP-2026-003","type":"free",     admin
                        "old_serial":"SN-152","new_serial":"SN-200"}
replacement_delivered  {"replacement_id":3,"number":"REP-2026-003","address":null}    admin
complaint_closed       {"complaint_id":4,"number":"CMP-2026-004",                     admin
                        "resolution":"replacement"}
```

```
● Complaint opened — CMP-2026-004
  SN-152 · Widget Max · not working
  May 14  10:00 AM  ·  by Admin Sarah

● Unit received — SN-152
  CMP-2026-004 · brought in at counter
  May 14  10:00 AM  ·  by Warehouse Sam

● Unit examined — internal issues
  CMP-2026-004 · SN-152 · warranty applies
  May 14  11:00 AM  ·  by Admin Sarah

● Replacement issued — REP-2026-003  (free)
  SN-152 → SN-200
  May 14  11:15 AM  ·  by Admin John

● Replacement delivered
  in-store handoff (no shipping address)
  May 14  11:15 AM  ·  by Admin John

● Complaint closed — replacement
  CMP-2026-004
  May 14  11:20 AM  ·  by Admin John
```

> `replacement_delivered` fires immediately on issue when customer is at the counter. `address` is `null` to signal in-store handoff.

---

### Scenario G4 — Complaint · withdrawn before shipping

**Flow:** `complaint_opened → return_label_sent → complaint_withdrawn`

Customer changes mind after label is sent — never ships unit.

```
event                metadata                                                       created_by
───────────────────  ─────────────────────────────────────────────────────────────  ──────────
complaint_opened     {"complaint_id":5,"number":"CMP-2026-005","serial":"SN-153",   admin
                      "sku":"PROD-A","type":"not_working"}
return_label_sent    {"complaint_id":5,"number":"CMP-2026-005","carrier":"FedEx",   admin
                      "label_cost":"8.50","shipment_id":52}
complaint_withdrawn  {"complaint_id":5,"number":"CMP-2026-005","label_voided":true} admin
```

```
● Complaint opened — CMP-2026-005
  SN-153 · Widget Pro · not working
  May 16  9:00 AM  ·  by Admin Sarah

● Return label sent — FedEx · label $8.50
  CMP-2026-005 · shipment #52
  May 16  9:30 AM  ·  by Admin Sarah

● Complaint withdrawn
  CMP-2026-005 · label voided
  May 17  3:00 PM  ·  by Admin Sarah
```

> `complaint_withdrawn` is terminal — no `complaint_closed` follows. `label_voided=true` indicates the return label was cancelled (no FedEx charge incurred).

---

### Scenario G5 — Complaint · return lost in transit

**Flow:** `complaint_opened → return_label_sent → return_lost`

Customer shipped unit back but it never arrived — carrier lost the package.

```
event              metadata                                                       created_by
─────────────────  ─────────────────────────────────────────────────────────────  ──────────
complaint_opened   {"complaint_id":6,"number":"CMP-2026-006","serial":"SN-154",   admin
                    "sku":"PROD-B","type":"defective_on_arrival"}
return_label_sent  {"complaint_id":6,"number":"CMP-2026-006","carrier":"FedEx",   admin
                    "label_cost":"8.50","shipment_id":53}
return_lost        {"complaint_id":6,"number":"CMP-2026-006","serial":"SN-154",   admin
                    "carrier":"FedEx","shipment_id":53}
```

```
● Complaint opened — CMP-2026-006
  SN-154 · Widget Max · defective on arrival
  May 18  10:00 AM  ·  by Admin Sarah

● Return label sent — FedEx · label $8.50
  CMP-2026-006 · shipment #53
  May 18  10:15 AM  ·  by Admin Sarah

● Return lost in transit — FedEx · shipment #53
  SN-154 · CMP-2026-006 · escalating to carrier claim
  May 25  4:00 PM  ·  by Admin Sarah
```

> `return_lost` is terminal — no `unit_examined` possible. Admin escalates to carrier claim separately. `inventory_serials.status` → `missing`.

---

### Scenario G6 — Complaint · resolved via refund (no replacement)

**Flow:** `complaint_opened → return_label_sent → unit_received → unit_examined → refunded → complaint_closed`

Customer asked for money back instead of replacement.

```
event              metadata                                                       created_by
─────────────────  ─────────────────────────────────────────────────────────────  ──────────
complaint_opened   {"complaint_id":7,"number":"CMP-2026-007","serial":"SN-155",   admin
                    "sku":"PROD-A","type":"not_working"}
return_label_sent  {"complaint_id":7,"number":"CMP-2026-007","carrier":"FedEx",   admin
                    "label_cost":"8.50","shipment_id":54}
unit_received      {"complaint_id":7,"number":"CMP-2026-007","serial":"SN-155"}   warehouse
unit_examined      {"complaint_id":7,"number":"CMP-2026-007","serial":"SN-155",   admin
                    "result":"internal_issues"}
refunded           {"amount":"220.00","refund_number":"REF-2026-002"}             admin
complaint_closed   {"complaint_id":7,"number":"CMP-2026-007",                     admin
                    "resolution":"refund"}
```

```
● Complaint opened — CMP-2026-007
  SN-155 · Widget Pro · not working
  May 20  9:00 AM  ·  by Admin Sarah

● Return label sent — FedEx · label $8.50
  CMP-2026-007 · shipment #54
  May 20  9:30 AM  ·  by Admin Sarah

● Unit received — SN-155
  CMP-2026-007
  May 23  10:00 AM  ·  by Warehouse Sam

● Unit examined — internal issues
  CMP-2026-007 · SN-155
  May 23  1:00 PM  ·  by Admin Sarah

● Refunded — $220.00
  REF-2026-002
  May 23  2:00 PM  ·  by Admin John

● Complaint closed — refund
  CMP-2026-007
  May 23  2:05 PM  ·  by Admin John
```

---

### Scenario H — Cancelled + refunded

**Flow:** `→ cancelled → refunded`

```
event      metadata                                                               created_by
─────────  ─────────────────────────────────────────────────────────────────────  ──────────
cancelled  {"reason":"Customer requested cancellation","was_status":"processing"} admin
refunded   {"amount":"240.00","refund_number":"REF-2026-001"}                     admin
```

```
● Order cancelled
  customer requested cancellation · was: processing
  May 18  10:00 AM  ·  by Admin John

● Refunded — $240.00
  REF-2026-001
  May 18  10:05 AM  ·  by Admin John
```

---

### Scenario I — Note added

```
event       metadata                                                              created_by
──────────  ──────────────────────────────────────────────────────────────────── ──────────
note_added  {"note_id":5,"preview":"Customer called — confirmed delivery         admin
             address is correct, expecting delivery by Friday"}
```

```
● Note added
  "Customer called — confirmed delivery address is correct, expecting delivery by Friday"
  May 17  3:00 PM  ·  by Admin Sarah
```

---

### Scenario J — Online order · website · Stripe Card · carrier delivery

**Source:** `online` — customer self-service via portal
**Payment:** `stripe_card` only (only method allowed for `online` source)
**Back orders:** not allowed — online orders require serial available at creation
**`created_by`:** customer's own `users.id` — no admin involved at placement

**Flow:** `order_placed → payment_received → shipped → delivered`

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ─────────────────────
order_placed      {"sku":"PROD-A","product_name":"Widget Pro","grand_total":"220.00"}  customer (user_id=25)
payment_received  {"method":"stripe_card","amount":"220.00",                      customer (user_id=25)
                   "subtotal":"200.00","fees":"0.00","shipping":"20.00"}
shipped           {"carrier":"FedEx","tracking":"FX-005","label_cost":"9.00",     warehouse staff
                   "address":"123 Main St, Phoenix AZ 85001","shipment_id":5}
delivered         {"address":"123 Main St, Phoenix AZ 85001","shipment_id":5}     admin
```

```
● Order placed — Widget Pro · PROD-A · $220.00
  May 10  3:00 PM  ·  by Customer (self-service)

● Payment received — $220.00 via Stripe Card
  subtotal $200.00 · no fees · shipping $20.00
  May 10  3:00 PM  ·  by Customer

● Shipped — FedEx · FX-005 · label $9.00
  to: 123 Main St, Phoenix AZ 85001
  May 11  9:00 AM  ·  by Warehouse Sam

● Delivered
  123 Main St, Phoenix AZ 85001
  May 13  2:00 PM  ·  by Admin John
```

> `created_by` = customer's `users.id` for `order_placed` and `payment_received` — customer self-service, no admin involved. Billing snapshot required — Stripe card-not-present needs billing address.

---

### Scenario K — Replacement charge-back

**Context:** customer's complaint examined → `no_fault_found` or `damaged_by_customer`. Replacement already shipped (Flow B) → admin now charges customer for the replacement. `payment_received` fires on the **original order** with `replacement_id` + `number` in metadata to correlate the charge.

**Flow (this scenario only adds one event to an existing complaint flow):** `… → payment_received` (with replacement metadata)

```
event             metadata                                                        created_by
────────────────  ──────────────────────────────────────────────────────────────  ──────────
payment_received  {"method":"stripe_terminal","amount":"150.00",                  admin
                   "subtotal":"150.00","fees":"0.00","shipping":"0.00",
                   "replacement_id":2,"number":"REP-2026-002"}
```

```
● Payment received — $150.00 via Stripe Terminal  (replacement charge-back)
  REP-2026-002 · subtotal $150.00 · no fees · no shipping
  May 12  3:00 PM  ·  by Admin John
```

> Single `payment_received` event covers both order payments and replacement charge-backs. The presence of `replacement_id` + `number` in metadata is how the UI/reports distinguish a charge-back from an order payment. `payments.order_id` = parent order ID; `payments.payable_type/payable_id` = `replacement`/`replacement.id`.

---

## Order Event Enum — Full Reference

25 events across 7 categories. Matches `app/Enums/OrderEvent.php` (cast on `order_events.event`).

### Order lifecycle

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `order_placed` | `OrderService::create()` — serial available at creation | admin / CSR / customer | `sku`, `product_name`, `grand_total` |
| `back_order_created` | `OrderService::create()` — `inventory_serial_id = NULL` at creation | admin / CSR | `sku`, `product_name`, `grand_total` |
| `serial_assigned` | `OrderService::assignSerial()` — back order serial filled from arriving PO | admin | `serial`, `sku`, `product_name`, `po` |
| `cancelled` | `OrderService::cancel()` | admin / CSR | `reason`, `was_status` |
| `completed` | `OrderService::complete()` — in-store pickup, customer collected at counter | admin | `{}` |

### Payment events

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `payment_received` | `PaymentService::record()` — cash / stripe_card / stripe_terminal. Also replacement charge-backs | admin / CSR / customer | `method`, `amount`, `subtotal`, `fees`, `shipping`. Add `replacement_id`, `number` for charge-backs |
| `payment_pending` | `PaymentService::createCheckout()` or cheque received | admin / CSR | `method`, `amount`, `session_id` (stripe_checkout) or `cheque_number` + `cheque_date` (cheque) |
| `payment_confirmed` | Stripe webhook `checkout.session.completed` OR `PaymentService::markChequeCleared()` | NULL (webhook) / admin | `method`, `session_id` or `cheque_number` |
| `payment_expired` | Stripe webhook `checkout.session.expired` — customer never paid | NULL (webhook) | `method`, `session_id`, `amount` |

### Shipment events (order)

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `shipped` | `ShipmentService::ship()` — outbound carrier shipment created | warehouse staff | `carrier`, `tracking`, `label_cost`, `address`, `shipment_id` |
| `delivered` | `ShipmentService::markDelivered()` | admin | `address`, `shipment_id` |
| `rts_triggered` | `ShipmentService::markReturned()` — carrier returned package to warehouse | admin | `carrier`, `tracking`, `shipment_id` |
| `re_shipped` | `ShipmentService::ship()` — second outbound shipment after RTS | warehouse staff | `carrier`, `tracking`, `label_cost`, `address`, `address_id`, `shipment_id` |

### Complaint events

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `complaint_opened` | `ComplaintService::create()` | admin / CSR | `complaint_id`, `number`, `serial`, `sku`, `type` |
| `return_label_sent` | `ComplaintService::generateLabel()` — prepaid return label sent to customer | admin | `complaint_id`, `number`, `carrier`, `label_cost`, `shipment_id` |
| `unit_received` | `ComplaintService::receiveUnit()` — returned unit arrived at warehouse | warehouse staff | `complaint_id`, `number`, `serial` |
| `unit_examined` | `ComplaintService::examine()` — examination result recorded | admin | `complaint_id`, `number`, `serial`, `result` (`internal_issues` / `damaged_by_customer` / `no_fault_found`) |
| `return_lost` | `ComplaintService::markReturnLost()` — inbound return lost in transit | admin | `complaint_id`, `number`, `serial`, `carrier`, `shipment_id` |
| `complaint_withdrawn` | `ComplaintService::withdraw()` — customer withdraws before shipping unit | admin / CSR | `complaint_id`, `number`, `label_voided` (bool) |
| `complaint_closed` | `ComplaintService::close()` — complaint resolved | admin | `complaint_id`, `number`, `resolution` (`replacement` / `refund` / `no_action`) |

### Replacement events

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `replacement_issued` | `ReplacementService::create()` | admin / CSR | `replacement_id`, `number`, `type` (`free` / `charged`), `old_serial`, `new_serial` |
| `replacement_shipped` | `ReplacementService::ship()` — replacement unit shipped via carrier | warehouse staff | `replacement_id`, `number`, `carrier`, `tracking`, `label_cost`, `address`, `shipment_id` |
| `replacement_delivered` | `ReplacementService::markDelivered()` — or immediately for in-store handoff | admin | `replacement_id`, `number`, `address` (NULL for in-store) |

### Resolution + notes

| Event | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `refunded` | `RefundService::process()` | admin | `amount`, `refund_number` |
| `note_added` | `NoteService::create()` — note attached to order | admin / CSR | `note_id`, `preview` |
