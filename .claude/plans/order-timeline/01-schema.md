# Order Timeline — Schema

## Purpose

Append-only event log recording every meaningful business event on an order. Powers the admin-facing order history timeline. Not a dev audit log — only named business events with structured metadata. Written manually in service methods inside `DB::transaction` — no model observers.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `order_events` | Append-only log of all significant order events |

---

## Table: `order_events`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id, cascade delete |
| event | string(50) | No | — | Cast to `OrderEvent` enum |
| metadata | json | Yes | null | Event-specific structured data |
| created_by | foreignId | Yes | null | FK → users.id — NULL = system / webhook |
| created_at | timestamp | No | — | Auto — **no `updated_at`** |

### Indexes
- `order_id` — foreign key index (auto)
- `(order_id, created_at)` — composite for ordered timeline query (primary use case)
- `event` — filter by event type for reports (e.g. all shipments today)
- `created_by` — foreign key + staff activity reports

### Notes
- Append-only — no updates, no deletes
- `created_by` NULL = system / webhook event (no human actor)
- Human-readable label computed in PHP from `event + metadata` — no `description` column
- Every service method that changes order state MUST dispatch an event row in the same `DB::transaction`
- Migration uses `$table->timestamp('created_at')->useCurrent()` only — **not** `$table->timestamps()`

---

## Event Enum: `OrderEvent`

### Order lifecycle

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `order_placed` | `OrderService::create()` — serial available at creation | admin / CSR / customer | `sku`, `product_name`, `grand_total` |
| `back_order_created` | `OrderService::create()` — `inventory_serial_id = NULL` at creation | admin / CSR | `sku`, `product_name`, `grand_total` |
| `serial_assigned` | `OrderService::assignSerial()` — back order serial filled from arriving PO | admin | `serial`, `sku`, `product_name`, `po` |
| `cancelled` | `OrderService::cancel()` | admin / CSR | `reason`, `was_status` |
| `completed` | `OrderService::complete()` — in-store pickup, customer collected at counter | admin | `{}` |

### Payment events

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `payment_received` | `PaymentService::record()` — cash / stripe_card / stripe_terminal. Also replacement charge-backs | admin / CSR / customer | `method`, `amount`, `subtotal`, `fees`, `shipping`. Add `replacement_id`, `number` for charge-backs |
| `payment_pending` | `PaymentService::createCheckout()` or cheque received | admin / CSR | `method`, `amount`, `session_id` (stripe_checkout) or `cheque_number` + `cheque_date` (cheque) |
| `payment_confirmed` | Stripe webhook `checkout.session.completed` OR `PaymentService::markChequeCleared()` | NULL (webhook) / admin | `method`, `session_id` or `cheque_number` |
| `payment_expired` | Stripe webhook `checkout.session.expired` — customer never paid | NULL (webhook) | `method`, `session_id`, `amount` |

### Shipment events (order)

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `shipped` | `ShipmentService::ship()` — outbound carrier shipment created | warehouse staff | `carrier`, `tracking`, `label_cost`, `address`, `shipment_id` |
| `delivered` | `ShipmentService::markDelivered()` | admin | `address`, `shipment_id` |
| `rts_triggered` | `ShipmentService::markReturned()` — carrier returned package to warehouse | admin | `carrier`, `tracking`, `shipment_id` |
| `re_shipped` | `ShipmentService::ship()` — second outbound shipment after RTS | warehouse staff | `carrier`, `tracking`, `label_cost`, `address`, `address_id`, `shipment_id` |

### Complaint events

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `complaint_opened` | `ComplaintService::create()` | admin / CSR | `complaint_id`, `number`, `serial`, `sku`, `type` |
| `return_label_sent` | `ComplaintService::generateLabel()` — prepaid return label sent to customer | admin | `complaint_id`, `number`, `carrier`, `label_cost`, `shipment_id` |
| `unit_received` | `ComplaintService::receiveUnit()` — returned unit arrived at warehouse | warehouse staff | `complaint_id`, `number`, `serial` |
| `unit_examined` | `ComplaintService::examine()` — examination result recorded | admin | `complaint_id`, `number`, `serial`, `result` (`internal_issues` / `damaged_by_customer` / `no_fault_found`) |
| `return_lost` | `ComplaintService::markReturnLost()` — inbound return lost in transit | admin | `complaint_id`, `number`, `serial`, `carrier`, `shipment_id` |
| `complaint_withdrawn` | `ComplaintService::withdraw()` — customer withdraws before shipping unit | admin / CSR | `complaint_id`, `number`, `label_voided` (bool) |
| `complaint_closed` | `ComplaintService::close()` — complaint resolved | admin | `complaint_id`, `number`, `resolution` (`replacement` / `refund` / `no_action`) |

### Replacement events

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `replacement_issued` | `ReplacementService::create()` | admin / CSR | `replacement_id`, `number`, `type` (`free` / `charged`), `old_serial`, `new_serial` |
| `replacement_shipped` | `ReplacementService::ship()` — replacement unit shipped via carrier | warehouse staff | `replacement_id`, `number`, `carrier`, `tracking`, `label_cost`, `address`, `shipment_id` |
| `replacement_delivered` | `ReplacementService::markDelivered()` — or immediately for in-store handoff | admin | `replacement_id`, `number`, `address` (NULL for in-store) |

### Resolution + notes

| Value | Trigger | `created_by` | `metadata` keys |
|-------|---------|-------------|----------------|
| `refunded` | `RefundService::process()` | admin | `amount`, `refund_number` |
| `note_added` | `NoteService::create()` — note attached to order | admin / CSR | `note_id`, `preview` |

---

## Metadata Shapes (Full Reference)

```json
// order_placed / back_order_created
{"sku":"PROD-A","product_name":"Widget Pro","grand_total":"220.00"}

// serial_assigned
{"serial":"SN-154","sku":"USB-C-HUB-7","product_name":"7-Port USB-C Hub","po":"PO-2026-013"}

// payment_received — order (instant)
{"method":"stripe_terminal","amount":"187.38","subtotal":"162.38","fees":"10.00","shipping":"15.00"}

// payment_received — replacement charge-back
{"method":"stripe_terminal","amount":"150.00","subtotal":"150.00","fees":"0.00","shipping":"0.00",
 "replacement_id":2,"number":"REP-2026-002"}

// payment_pending — stripe_checkout
{"method":"stripe_checkout","amount":"240.00","session_id":"cs_live_abc789xyz"}

// payment_pending — cheque
{"method":"cheque","amount":"300.00","cheque_number":"1042","cheque_date":"2026-05-05"}

// payment_confirmed — stripe_checkout (created_by = NULL — webhook)
{"method":"stripe_checkout","session_id":"cs_live_abc789xyz"}

// payment_confirmed — cheque (created_by = admin)
{"method":"cheque","cheque_number":"1042"}

// payment_expired — stripe_checkout (created_by = NULL — webhook)
{"method":"stripe_checkout","session_id":"cs_live_abc789xyz","amount":"240.00"}

// shipped
{"carrier":"FedEx","tracking":"7489234721034","label_cost":"9.00",
 "address":"321 Birch Ave, Tucson AZ 85701","shipment_id":41}

// delivered
{"address":"321 Birch Ave, Tucson AZ 85701","shipment_id":41}

// rts_triggered
{"carrier":"FedEx","tracking":"FX-001","shipment_id":41}

// re_shipped
{"carrier":"FedEx","tracking":"FX-002","label_cost":"8.50",
 "address":"789 Pine Ave Suite 100, Chicago IL 60602","address_id":15,"shipment_id":42}

// cancelled
{"reason":"Customer requested cancellation","was_status":"processing"}

// complaint_opened
{"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151","sku":"PROD-A","type":"defective_on_arrival"}

// return_label_sent
{"complaint_id":3,"number":"CMP-2026-003","carrier":"FedEx","label_cost":"8.50","shipment_id":50}

// unit_received
{"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151"}

// unit_examined
{"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151","result":"internal_issues"}

// return_lost
{"complaint_id":3,"number":"CMP-2026-003","serial":"SN-151","carrier":"FedEx","shipment_id":50}

// complaint_withdrawn
{"complaint_id":3,"number":"CMP-2026-003","label_voided":true}

// complaint_closed
{"complaint_id":3,"number":"CMP-2026-003","resolution":"replacement"}

// replacement_issued
{"replacement_id":2,"number":"REP-2026-002","type":"free","old_serial":"SN-151","new_serial":"SN-199"}

// replacement_shipped
{"replacement_id":2,"number":"REP-2026-002","carrier":"FedEx","tracking":"FX-010",
 "label_cost":"9.00","address":"321 Oak Lane, Dallas TX 75201","shipment_id":51}

// replacement_delivered — carrier
{"replacement_id":2,"number":"REP-2026-002","address":"321 Oak Lane, Dallas TX 75201"}

// replacement_delivered — in-store handoff
{"replacement_id":2,"number":"REP-2026-002","address":null}

// refunded
{"amount":"240.00","refund_number":"REF-2026-001"}

// note_added
{"note_id":5,"preview":"Customer called — confirmed delivery address is correct..."}
```

---

## Flow Reference

| Scenario | Event sequence |
|----------|---------------|
| Normal order — carrier | `order_placed → payment_received → shipped → delivered` |
| Normal order — in-store pickup | `order_placed → payment_received → completed` |
| Online order — stripe_card — carrier | `order_placed → payment_received → shipped → delivered` |
| Stripe Checkout — async | `order_placed → payment_pending → payment_confirmed → shipped → delivered` |
| Stripe Checkout — expired | `order_placed → payment_pending → payment_expired → cancelled` |
| Cheque — async | `order_placed → payment_pending → payment_confirmed → shipped → delivered` |
| Carrier + RTS | `order_placed → payment_received → shipped → rts_triggered → re_shipped → delivered` |
| Back order — prepaid cash | `back_order_created → payment_received → serial_assigned → shipped → delivered` |
| Back order — stripe_checkout prepaid | `back_order_created → payment_pending → payment_confirmed → serial_assigned → shipped → delivered` |
| Back order — pay at pickup | `back_order_created → serial_assigned → payment_received → completed` |
| Back order — pay when stock arrives | `back_order_created → serial_assigned → payment_received → shipped → delivered` |
| Complaint — return + replacement | `complaint_opened → return_label_sent → unit_received → unit_examined → replacement_issued → replacement_shipped → replacement_delivered → complaint_closed` |
| Complaint — in-store handoff + replacement | `complaint_opened → unit_received → unit_examined → replacement_issued → replacement_delivered → complaint_closed` |
| Complaint — withdrawn | `complaint_opened → return_label_sent → complaint_withdrawn` |
| Complaint — return lost | `complaint_opened → return_label_sent → return_lost` |
| Complaint — resolved via refund | `complaint_opened → return_label_sent → unit_received → unit_examined → refunded → complaint_closed` |
| Replacement charge-back | `→ payment_received` (with `replacement_id` + `number` in metadata) |
| Cancelled + refunded | `→ cancelled → refunded` |

---

## Service Layer Pattern

```php
DB::transaction(function () use ($order, $shipment, $user) {
    $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now(), 'shipped_by' => $user->id]);

    OrderEvent::create([
        'order_id'   => $order->id,
        'event'      => OrderEvent::Shipped,
        'metadata'   => [
            'carrier'     => $shipment->carrier,
            'tracking'    => $shipment->tracking,
            'label_cost'  => (string) $shipment->label_cost,
            'address'     => $address,
            'shipment_id' => $shipment->id,
        ],
        'created_by' => $user->id,
    ]);
});
```

> `created_by` NULL for system/webhook events. Pass `null` explicitly — never use `auth()->id()` in webhook handlers.

---

## Migration Order

```
1. orders        (order_id FK)
2. users         (created_by FK)
3. order_events  (depends on: orders, users)
```

---

## Relationships Summary

```
Order hasMany OrderEvents
OrderEvent belongsTo Order
OrderEvent belongsTo User (created_by — nullable)
```
