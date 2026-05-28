# Order System — Schema (Text Diagram)

Derived from `examples/ex-19-walkin-cash-instore-pickup.md` (canonical) and all 32 example files. Uses `order_line_fees` (per-line), not deprecated `order_fees`.

---

## Big Picture

```
                      ┌─────────────┐
                      │ departments │
                      └──────┬──────┘
                             │ 1:N
                      ┌──────▼──────┐
                      │    users    │ (staff: admin, sales)
                      └──────┬──────┘
                             │ created_by / shipped_by / delivered_by / examined_by
                             │
        ┌────────────────────┼──────────────────────────┐
        │                    │                          │
        ▼                    ▼                          ▼
 ┌─────────────┐      ┌─────────────┐           ┌──────────────┐
 │  customers  │─1:N─▶│   orders    │◀──N:1─────│ product_     │
 └──────┬──────┘      └──────┬──────┘           │  listings    │
        │ 1:N                │ 1:N              └──────┬───────┘
        ▼                    ▼                         │ 1:N
 ┌─────────────────┐  ┌─────────────┐                  ▼
 │ customer_       │  │ order_lines │◀──N:1──┐  ┌──────────────┐
 │  addresses      │  └──────┬──────┘        │  │ inventory_   │
 └─────────────────┘         │ 1:N           └──│  serials     │
                             ▼                  └──────┬───────┘
                      ┌──────────────────┐             │ 1:N
                      │ order_line_fees  │             ▼
                      └──────────────────┘     ┌──────────────────┐
                                               │ inventory_       │
 ┌─────────────┐      ┌─────────────┐          │  movements       │
 │  payments   │◀─────│   orders    │          └──────────────────┘
 └─────────────┘      │             │                  ▲
 polymorphic:         │             │                  │ from/to
 order|replacement|   └─────────────┘                  │
 core_return                                    ┌──────┴────────┐
                                                │ inventory_    │
 ┌─────────────┐                                │  locations    │
 │  shipments  │  polymorphic:                  └───────────────┘
 └─────────────┘  order | complaint |
                  replacement | core_return
```

---

## Order → Complaint → Replacement Chain

```
 orders
   │
   ├─▶ order_lines ─────┐
   │      │             │
   │      └─▶ order_line_fees
   │
   ├─▶ payments  (payable_type = order)
   │
   ├─▶ shipments (shippable_type = order, direction = outbound)
   │
   ├─▶ order_events
   │
   ├─▶ notes
   │
   └─▶ complaints
          │
          ├─ on order_line + inventory_serial
          │
          ├─▶ shipments (shippable_type = complaint, direction = inbound|outbound)
          │
          └─▶ replacements
                 │
                 ├─▶ replacement_lines (old_serial → new_serial)
                 │
                 ├─▶ payments (payable_type = replacement, when charged)
                 │
                 └─▶ shipments (shippable_type = replacement, direction = outbound)
```

---

## Inventory Chain

```
 product_listings
        │
        └─▶ inventory_serials  (one row per physical unit)
                   │
                   │  status: in_stock → sold → expected_return →
                   │          under_examination → scrapped|sold|in_stock
                   │
                   └─▶ inventory_movements  (audit trail)
                          types: receive · sale · return_in · transfer ·
                                 replacement_out · adjustment
                          from_location_id ──▶ to_location_id
                                                (inventory_locations)
```

---

## Core Returns (cr-* examples)

```
 customers ──▶ core_returns ◀── orders / order_lines / inventory_serials
                    │
                    ├─▶ order_core_charges  (held → refunded | forfeited)
                    │
                    ├─▶ shipments (shippable_type = core_return)
                    │
                    └─▶ inventory_movements (reference = CR-xxx)

 channel:      counter | mail
 disposition:  rebuild | reclaim | scrapped | disposed | shipback | takeback
```

---

## Table → Columns (short)

```
customers          : id · name · email · phone · status · deleted_at
customer_addresses : id · customer_id · label · first/last_name · email · phone
                     · address_line1/2 · city · state · postal_code · country · is_default

departments        : id · name · code · is_active
users              : id · name · email · department_id · job_title · status
roles              : id · name · guard_name           (Spatie)
permissions        : id · name · guard_name           (Spatie)
model_has_roles    : role_id · model_type · model_id  (Spatie pivot)

product_listings   : id · sku · name · unit_price · tax_code

orders             : id · number · customer_id · source · status · payment_status
                     · shipping · grand_total · billing_snapshot · shipping_snapshot
                     · shipped_at/by · delivered_at/by · cancelled_at/by · created_by

order_lines        : id · order_id · product_listing_id · sku · product_name
                     · inventory_serial_id · unit_price · tax_rate · tax_amount · line_total

order_line_fees    : id · order_line_id · name · amount · tax_amount · fee_total
                     · created_by · created_at

payments           : id · order_id · payable_type · payable_id · method · amount
                     · status · cash_received_at · stripe_* fields

shipments          : id · shippable_type · shippable_id · customer_address_id
                     · direction · carrier · tracking · label_cost · status
                     · shipped_at · delivered_at · returned_at

order_events       : id · order_id · event_type · actor_id · payload · created_at

notes              : id · order_id · body · created_by · created_at

complaints         : id · number · order_id · order_line_id · inventory_serial_id
                     · status · examination_result · unit_outcome
                     · issue_description · unit_received_at · examined_by
                     · examination_notes · closed_at/by · created_by · withdrawn_at/by

replacements       : id · number · order_id · parent_id · complaint_id
                     · type · charge · pay_status · status

replacement_lines  : id · replacement_id · order_line_id · sku · product_name
                     · old_inventory_serial_id · new_inventory_serial_id

inventory_locations: id · name · is_active
inventory_serials  : id · product_listing_id · serial_number · status
                     · inventory_location_id · notes
inventory_movements: id · inventory_serial_id · type · from_location_id
                     · to_location_id · reference · notes · created_by · created_at

core_returns       : id · number · customer_id · order_id · order_line_id
                     · inventory_serial_id · channel · accept_status · disposition
                     · status · received_at · examined_by · closed_at
order_core_charges : id · order_id · order_line_id · core_return_id · amount · status
```

---

## Enums

```
orders.status              pending · back_ordered · processing · shipped · complete
                           · cancelled · refunded · rts
orders.payment_status      unpaid · paid
orders.source              walk_in · phone · online

payments.method            cash · stripe_card · stripe_terminal · stripe_checkout · cheque
payments.status            paid · refunded
payments.payable_type      order · replacement · core_return

shipments.direction        outbound · inbound
shipments.status           pending · shipped · delivered · returned
shipments.shippable_type   order · complaint · replacement · core_return

complaints.status              open · in_progress · closed · withdrawn
complaints.examination_result  internal_issues · damaged_by_customer · no_fault_found
complaints.unit_outcome        scrapped · returned_to_customer · back_to_stock

replacements.type   free · charged
replacements.status pending · assigned · shipped · delivered

inventory_serials.status   in_stock · sold · assigned · expected_return
                           · under_examination · scrapped · missing
inventory_movements.type   receive · sale · return_in · transfer
                           · replacement_out · adjustment

core_returns.channel       counter · mail
core_returns.disposition   rebuild · reclaim · scrapped · disposed · shipback · takeback
core_returns.status        pending · received · examined · closed
order_core_charges.status  held · refunded · forfeited
```

---

## Locked v1 Scope (walk-in + phone, cash only)

Active tables only:

```
 customers ─▶ customer_addresses
     │
     ▼
   orders ─▶ order_lines ─▶ order_line_fees
     │           │
     │           └─▶ inventory_serials ─▶ inventory_movements ─▶ inventory_locations
     │
     ├─▶ payments (cash only)
     ├─▶ shipments (outbound, optional — pickup has none)
     ├─▶ order_events
     └─▶ notes

 orders.status: pending → processing → complete
 orders.source: walk_in | phone
 payments.method: cash
```

Parked for later: complaints, replacements, core_returns, online channel, stripe/cheque payments, refunded/cancelled/rts statuses.
