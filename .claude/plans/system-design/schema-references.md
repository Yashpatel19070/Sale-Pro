# Order System — Schema References

Worked examples. Schema + data + flow. No extras.

---

## Agent Rules — Read This First

### Column name convention
Example tables use **simplified display names** for readability. Actual DB column names differ. Always check the migration file for the real column name before writing any query or code.

| Example shows | Actual DB column | Table |
|---------------|-----------------|-------|
| `serial` | `serial_number` | `inventory_serials` |
| `location` | `inventory_location_id` (FK → `inventory_locations`) | `inventory_serials` |
| `note` | `notes` | `inventory_serials` |
| `serial` | `inventory_serial_id` (FK → `inventory_serials`) | `inventory_movements` |
| `from` | `from_location_id` (FK → `inventory_locations`) | `inventory_movements` |
| `to` | `to_location_id` (FK → `inventory_locations`) | `inventory_movements` |
| `order` | `order_id` (FK → `orders`) | `order_lines`, `order_fees`, `payments`, etc. |
| `parent` | `parent_id` (FK → `replacements`) | `replacements` |
| `line` | `order_line_id` (FK → `order_lines`) | `complaints` |
| `complaint` | `complaint_id` (FK → `complaints`) | `replacements` |
| `rep` | `replacement_id` (FK → `replacements`) | `replacement_lines` |
| `order_line` | `order_line_id` (FK → `order_lines`) | `replacement_lines` |

### FK value convention
FK columns show **referenced display values** in examples, not raw integer IDs.

| Example value | What DB actually stores |
|---------------|------------------------|
| `SN-001` in `inventory_serial_id` | integer FK e.g. `1` |
| `Warehouse A` in `from_location_id` | integer FK e.g. `2` |
| `ORD-2026-001` in `reference` | string — stored as-is ✓ |

### PHP enum files
| Status/type column | PHP enum | DB constraint |
|--------------------|----------|---------------|
| `inventory_serials.status` | `app/Enums/SerialStatus.php` | `string(50)` — PHP is the guard |
| `inventory_movements.type` | `app/Enums/MovementType.php` | MySQL ENUM — DB enforces values |

### Migration files
| Table | Migration |
|-------|-----------|
| `inventory_locations` | `2026_04_14_180000_create_inventory_locations_table.php` |
| `inventory_serials` | `2026_04_14_181000_create_inventory_serials_table.php` |
| `inventory_movements` | `2026_04_14_182000_create_inventory_movements_table.php` |

### Shipment delivery address vs order snapshot
- `orders.shipping_snapshot` — JSON captured at order creation; **immutable after placement**. Reflects customer's intended delivery address when they placed the order.
- `shipments.customer_address_id` — FK → `customer_addresses`; the actual address used **for that shipment attempt**. For re-ship after RTS, the new shipment row references a different `customer_address_id` than the original — do NOT read the order snapshot for the re-ship address.

> Query pattern: `JOIN customer_addresses ON shipments.customer_address_id = customer_addresses.id`

> **Shipment examples (Ex 1–13):** `customer_address_id` is omitted from shipment tables for brevity. In real data, all outbound carrier shipments require `customer_address_id` FK → `customer_addresses`. Inbound returns and in-store pickup shipments use NULL. See Example 14 for the full pattern including RTS re-ship with two different address IDs.

### payments.order_id — always populated
`payments.order_id` (FK → `orders`) is set on every payment row, regardless of `payable_type`:
- `payable_type = order` → `order_id = payable_id` (same value, order paid directly)
- `payable_type = replacement` → `order_id = parent order` (replacement charged back to original order)

> Use `WHERE payments.order_id = ?` to fetch **all** payments for an order (direct + replacement charges) without JOINing through `replacements`.

### order_lines — one serial per line
`order_lines` has no `quantity` column. One line = one physical unit = one serial number. Multiple units on a single order = multiple `order_lines` rows. This is by design — each serial has its own complaint, replacement, and examination lifecycle. Grouping units into one line with qty > 1 would break complaint tracking.

### inventory_movements — example IDs are illustrative
IDs in examples are for reference within each order/complaint chain. Within a single chain, IDs are chronological. Cross-example IDs do not reflect exact global insertion order — concurrent orders interleave in real insertion sequence. Never hardcode example IDs in queries or seeds.

### Example data is illustrative only
All IDs, timestamps, amounts, serial numbers, and sequence numbers in these examples are dummy values chosen for readability. They do not reflect real insertion order, global auto-increment sequences, or production data. Never infer "there must be N prior records" from an ID value — IDs are illustrative, not authoritative.

### orders.created_by — always a staff user
`orders.created_by` (FK → `users`) is set on every order. All orders are admin/CSR-created — online orders via the admin panel, walk_in and phone at the counter. Never NULL.

---

## Payment Rule (Global)

```
No payment  → order NOT placed
Full payment received → order placed → processing → ships
```

`orders.payment_status` = `unpaid | paid` only. No partial.

---

## Global Reference — Staff Users

Used across all examples. Role assigned via Spatie `model_has_roles` pivot — not a column on `users`.

**`departments`**
```
id  name              code   is_active
1   Administration    ADMIN  true
2   Warehouse         WH     true
3   Technical Repair  TECH   true
4   Customer Service  CS     true
```

**`users`**
```
id  name        email               department_id  job_title          status
1   Admin User  admin@salepro.com   1              Administrator      active
2   Ali Hassan  ali@salepro.com     2              Warehouse Staff    active
3   Sam Chen    sam@salepro.com     3              Technician         active
4   Raj Patel   raj@salepro.com     4              CS Representative  active
```

Roles (via pivot):
- User 1 → `admin`
- Users 2, 3, 4 → `sales`

## Global Reference — Status Enums

### `orders.status`
| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `pending` | Order created — payment not received, serial not yet assigned | no |
| `back_ordered` | Serial not assigned (stock not in) — payment may or may not be received | no |
| `processing` | Payment received + all lines have serial assigned — ready to ship/pickup | no |
| `shipped` | Carrier has the package (UPS/FedEx) — no delivery confirmation in system | no |
| `complete` | In-store pickup — customer collected at counter | no |
| `cancelled` | Cancelled before shipment/pickup | yes |
| `refunded` | Full refund issued — post-delivery return or post-pickup complaint escalation | yes |
| `rts` | Return-to-sender — carrier returning package to warehouse | no |

> `back_ordered` applies to `walk_in` and `phone` sources only — admin-created orders where stock is not immediately available. Online orders cannot be back-ordered.
> Payment may be taken at order creation (prepaid) or later (at pickup/delivery). `payment_status` tracks this independently — no partial payments.
> Advance to `processing` requires BOTH: `payment_status = paid` AND all `order_lines.inventory_serial_id` set.
> `shipped` stays as final status for carrier orders — admin records `delivered_at` manually, no status change on delivery.
> `complete` stays as final status for pickup orders — transitions to `refunded` only if admin issues a refund after complaint escalation. Orders have no `closed` status — `closed` belongs to `complaints` only.
> `cancelled_at` column reused as terminal timestamp for both `cancelled` and `refunded` states.

### `complaints.status`
| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `open` | Complaint created, unit not yet received | no |
| `in_progress` | Unit received (Flow A) or replacement shipped (Flow B) | no |
| `closed` | Resolved — examination complete, outcome recorded | yes |
| `withdrawn` | Customer withdrew complaint before examination | yes |

> Flow A: `in_progress` set when unit physically arrives (`return_in` movement recorded).
> Flow B: `in_progress` set when replacement ships — old unit not yet received.

### `complaints.examination_result`
| Value | Meaning |
|-------|---------|
| `internal_issues` | Internal component failure or manufacturing defect — warranty applies |
| `damaged_by_customer` | Physical damage caused by customer — warranty voided |
| `no_fault_found` | Unit fully functional — no defect detected |
| `NULL` | Not yet examined, or complaint withdrawn before examination |

### `complaints.unit_outcome`
| Value | Meaning |
|-------|---------|
| `scrapped` | Unit destroyed — confirmed fault or unrecoverable damage |
| `returned_to_customer` | Unit handed back to customer — no fault found |
| `back_to_stock` | Unit returned to warehouse inventory — no fault, unit reusable |
| `NULL` | Not yet determined, or complaint withdrawn before examination |

### `inventory_serials.status`
| Value | Meaning |
|-------|---------|
| `in_stock` | Available in warehouse, assignable to orders |
| `sold` | With customer |
| `assigned` | In transit as replacement — shipped, not yet with customer |
| `expected_return` | Return requested — awaiting inbound shipment from customer |
| `under_examination` | Unit received, being examined by technician |
| `scrapped` | Written off — internal fault, customer damage, or warehouse write-off |
| `missing` | Expected return never arrived — written off after admin decision |

---

## Global Reference — Inventory Movements

> **Code references:**
> - Movement types: `app/Enums/MovementType.php` — PHP enum backing `inventory_movements.type` MySQL ENUM
> - Serial statuses: `app/Enums/SerialStatus.php` — PHP enum backing `inventory_serials.status` string column
> - Locations: `inventory_locations` DB table — `from_location_id` / `to_location_id` are FK integers pointing to this table. Location names shown in examples are display values (`inventory_locations.name`), not raw column values.

> **ID ordering note:** movement IDs in examples are illustrative. Within a single order/complaint chain they are chronological. Cross-example IDs may not reflect exact global insertion order — 14 concurrent orders interleave in real sequence. `--` rows represent intermediate IDs belonging to other orders' movements in the global ledger.

### `inventory_movements.type`

| Type | PHP case | Meaning |
|------|----------|---------|
| `receive` | `MovementType::Receive` | PO receiving — new stock arriving from supplier |
| `sale` | `MovementType::Sale` | Unit sold — leaves warehouse to customer |
| `return_in` | `MovementType::ReturnIn` | Customer return inbound — first movement when package arrives at dock |
| `transfer` | `MovementType::Transfer` | Internal location move — no ownership change |
| `replacement_out` | `MovementType::ReplacementOut` | Replacement unit shipped to customer — separate from `sale` for reporting |
| `adjustment` | `MovementType::Adjustment` | Write-off, back to stock, or scrap — see sub-cases below |

---

### `adjustment` sub-cases

`adjustment` is reused for 4 distinct operations. Never query `type = 'adjustment'` alone — always filter further using `to_location_id` and `reference`:

| `to_location_id` | `reference` | Operation | How to identify |
|------------------|-------------|-----------|-----------------|
| set | CMP-xxx | **Back to stock** — no fault found, unit returned to sellable inventory | `to_location_id IS NOT NULL` |
| `NULL` | CMP-xxx | **Scrapped** — unit destroyed, internal fault or customer damage confirmed | join `complaints.unit_outcome = 'scrapped'` |
| `NULL` | CMP-xxx | **Returned to customer** — unit handed back after no fault found | join `complaints.unit_outcome = 'returned_to_customer'` |
| `NULL` | `NULL` | **Warehouse write-off** — pre-sale damage, no complaint, no order | `reference IS NULL` |

> **Agent rule:** for `type = 'adjustment' AND reference IS NOT NULL AND to_location_id IS NULL` — always join `complaints` on `complaints.number = reference` to determine outcome. Never assume scrapped without confirming `unit_outcome`.

---

### Reporting queries

Use these patterns for accurate inventory reports. Do not use `type = 'adjustment'` alone.

```sql
-- Scrapped units from complaints (internal fault or customer damage confirmed by tech)
SELECT im.inventory_serial_id, im.reference, c.examination_result, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NULL
  AND c.unit_outcome = 'scrapped';

-- Units returned to customer after examination (no fault found, unit handed back)
SELECT im.inventory_serial_id, im.reference, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NULL
  AND c.unit_outcome = 'returned_to_customer';

-- Units returned to stock after examination (no fault found, unit reusable)
SELECT im.inventory_serial_id, im.reference, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NOT NULL
  AND c.unit_outcome = 'back_to_stock';

-- Warehouse write-offs (no complaint, no order — pre-sale damage)
SELECT im.inventory_serial_id, im.notes, im.created_at
FROM inventory_movements im
WHERE im.type = 'adjustment'
  AND im.reference IS NULL;
```

---

## Example 1 — ORD-001 — Clean Stripe Card Order

**Scenario:** Sarah Johnson buys one item online. Pays full via Stripe card. Delivered. No issues.

---

### Data Flow

```
[Admin creates order]
        │
        ├──→ orders (customer_id=1, billing + shipping snapshot copied from customer_addresses, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_fees (service fee)

[Customer pays via Stripe card — sync]
        │
        └──→ payments INSERT (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped — no status change)
```

---

### Schema + Data

**`customers`**
```
id  name           email              phone         status
1   Sarah Johnson  sarah@example.com  555-100-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  address_line2  city    state  postal_code  country  is_default
1   1            Home   Sarah       Johnson    sarah@example.com  555-100-0001  123 Main St    NULL           Austin  TX     78701        US       true
```

**`orders`**
```
id  number        customer_id  source  status     payment_status  subtotal  fees   shipping  grand_total
1   ORD-2026-001  1            online  shipped    paid            200.00    20.00  20.00     240.00

-- billing snapshot (copied from customer_addresses at order creation)
billing_first_name  billing_last_name  billing_email      billing_phone  billing_address_line1  billing_address_line2  billing_city  billing_state  billing_postal_code  billing_country
Sarah               Johnson            sarah@example.com  555-100-0001   123 Main St            NULL                   Austin        TX             78701                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Sarah                Johnson             sarah@example.com  555-100-0001    123 Main St             NULL                    Austin         TX              78701                 US
```

One row in DB — split for readability. Address fields nullable — filled based on what customer provides.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
1   1      PROD-A  Widget Pro    SN-001  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
1   1      Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
1   1         order         1           stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
1   order           1             outbound   FedEx    FX-10001   8.50        delivered  2026-04-20 09:00  NULL                  2026-04-22 14:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-001  sold    NULL      with Sarah Johnson
```

**`inventory_movements`**
```
id  serial  type  from         to    reference     notes
1   SN-001  sale  Warehouse A  NULL  ORD-2026-001
```

---

### Financial Summary
```
charged:   $240.00
collected: $240.00
refunded:  $0.00
net:       $240.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $8.50   (shipments.label_cost)
margin:   +$11.50
```

---

## Example 2 — ORD-002 — Clean Cash Order

**Scenario:** Mike Torres walks in. Buys two items. Pays full cash at counter. Wants home delivery — provides address at counter. Admin records address, payment, ships via FedEx. No issues.

---

### Data Flow

```
[Customer walks in — admin creates order]
        │
        ├──→ customer_addresses INSERT (Mike gives home address for delivery)
        ├──→ orders (customer_id=2, billing snapshot NULL — cash, shipping snapshot copied from address, status=pending, payment_status=unpaid)
        ├──→ order_lines (2 line items)
        └──→ order_fees (service fee)

[Customer pays cash in full at counter — admin records]
        │
        └──→ payments INSERT (status=paid, cash_received_at=now)
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale × 2)
             inventory_serials UPDATE × 2 (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped — no status change)
```

---

### Schema + Data

**`customers`**
```
id  name        email             phone         status
2   Mike Torres mike@example.com  555-100-0002  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1   address_line2  city     state  postal_code  country  is_default
2   2            Home   Mike        Torres     mike@example.com  555-100-0002  456 Oak Avenue  NULL           Houston  TX     77001        US       true
```

**`orders`**
```
id  number        customer_id  source   status     payment_status  subtotal  fees   shipping  grand_total
2   ORD-2026-002  2            walk_in  shipped    paid            350.00    30.00  15.00     395.00

-- billing snapshot (NULL — cash payment, no billing address required)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses at order creation — delivery required)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Mike                 Torres              mike@example.com  555-100-0002    456 Oak Avenue          NULL                    Houston        TX              77001                 US
```

Billing NULL — cash payment, no billing address required. Shipping filled — walk-in customer provided home address for FedEx delivery. Both NULL not allowed when a carrier shipment exists.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
2   2      PROD-A  Widget Pro    SN-010  200.00      0.0000    0.00        200.00
3   2      PROD-B  Widget Basic  SN-011  150.00      0.0000    0.00        150.00
```

**`order_fees`**
```
id  order  name          amount
2   2      Service Fee   30.00
```

**Grand total**
```
subtotal $350 (200+150) + fees $30 + shipping $15 + tax $0 = $395 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
2   2         order         2           cash    395.00  paid    2026-04-21 10:30
```

One row. Full amount. Cash in hand before order processes.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
2   order           2             outbound   FedEx    FX-10002   12.00       delivered  2026-04-21 11:00  NULL                  2026-04-23 11:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-010  sold    NULL      with Mike Torres
SN-011  sold    NULL      with Mike Torres
```

**`inventory_movements`**
```
id  serial  type  from         to    reference     notes
2   SN-010  sale  Warehouse A  NULL  ORD-2026-002
3   SN-011  sale  Warehouse A  NULL  ORD-2026-002
```

---

### Financial Summary
```
charged:   $395.00
collected: $395.00
refunded:  $0.00
net:       $395.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $12.00  (shipments.label_cost)
margin:   +$3.00
```

---

## Key Differences — Examples 1, 2, 3 (ORD-001, ORD-002, ORD-004)

| | ORD-001 Sarah | ORD-002 Mike | ORD-004 Karen |
|---|---|---|---|
| Source | online | walk_in | walk_in |
| Payment method | stripe_card | cash | cash |
| Payment timing | sync via Stripe | at counter before ship | at counter before ship |
| Delivery | shipped, $20 charged | shipped, $15 charged | shipped, $20 charged |
| Line items | 1 | 2 | 1 |
| Inventory movements | 1 | 2 | 5 (sale + complaint chain: return_in, 2× transfer, adjustment) |
| customer_addresses | 1 row (Home) | 1 row (Home) | 1 row (Home) |
| Billing snapshot | filled (card — Stripe requires) | NULL (cash) | NULL (cash) |
| Shipping snapshot | filled | filled (provided for delivery) | filled (delivery requested) |
| Has complaint | no | no | yes — Flow A, no fault, returned |

> Billing snapshot: `stripe_card` (online) = filled; all walk_in/phone methods (cash, stripe_terminal, stripe_checkout, cheque) = NULL.
> Shipping snapshot: required whenever a carrier shipment exists. Both NULL only allowed for in-store pickup (no shipment row). Source (`walk_in`, `online`, `phone`) does not determine shipping — customer choice does.
> ORD-2026-003 is intentionally absent — no new patterns beyond Examples 1 and 2.

---

## Example 3 — ORD-004 — Flow A: No Fault, Unit Returned

**Scenario:** Karen White walks into the store. Pays cash at counter. Asks for delivery to her home — admin fills shipping address and ships. Delivered. Karen complains screen is flickering. Ships unit back. Tech examines — no defect found. Unit returned to Karen. No replacement, no refund.

> **Note — cash + delivery:** Karen is `source=walk_in` (paid cash at counter) but chose home delivery. Cash payment ≠ store pickup. These are independent decisions. Billing snapshot is NULL (cash — no card billing address needed). Shipping snapshot is filled (delivery to home requested). This pattern applies to any walk-in customer who wants items shipped.

---

### Data Flow

```
[Karen walks in — admin creates order]
        │
        ├──→ orders (customer_id=3, billing NULL—cash, shipping snapshot filled—delivery to home, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_fees (service fee)

[Karen pays cash at counter — admin records]
        │
        └──→ payments INSERT (status=paid, cash_received_at=2026-04-23 08:00)
             orders.payment_status → paid
             orders.status → processing

[Admin ships same day — Ali Hassan (Warehouse)]
        │
        └──→ shipments INSERT id=4 (direction=outbound, FedEx FX-10004)
             inventory_movements INSERT id=5 (sale, Warehouse A → NULL)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at = 2026-04-23 09:00, shipped_by = 2

[Delivered Apr 25 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at = 2026-04-25 12:00, delivered_by = 1
             (orders.status stays shipped — no status change)

[Raj Patel (CS) logs complaint — screen flickering]
        │
        └──→ complaints INSERT (status=open, created_by=4)
             inventory_serials UPDATE (sold → expected_return)

[Karen ships unit back — Apr 30, prepaid label]
        │
        └──→ shipments INSERT id=13 (direction=inbound, FedEx FX-20002)
             inventory_movements INSERT id=18 (return_in, NULL → Receiving Area)
             inventory_serials UPDATE (expected_return → under_examination)
             complaints.status → in_progress

[Sam Chen (Tech) examines — no fault found]
        │
        └──→ inventory_movements INSERT (transfer, Receiving Area → Tech Area)
             inventory_movements INSERT (transfer, Tech Area → Shipping Area)
             complaints.examination_result → no_fault_found
             complaints.examined_by = 3, examination_notes set

[Admin closes complaint — unit returned to Karen, May 2]
        │
        └──→ shipments INSERT id=17 (direction=outbound, FedEx FX-30002)
             inventory_movements INSERT id=34 (adjustment, Shipping Area → NULL)
             inventory_serials UPDATE (under_examination → sold)
             complaints.status → closed
             complaints.unit_outcome → returned_to_customer
             complaints.closed_at = 2026-05-02, closed_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name         email              phone         status
3   Karen White  karen@example.com  555-100-0003  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1   address_line2  city    state  postal_code  country  is_default
3   3            Home   Karen       White      karen@example.com  555-100-0003  456 Oak Avenue  NULL           Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
4   ORD-2026-004  3            walk_in  shipped  paid            200.00    30.00  20.00     250.00       2026-04-23 09:00      2           2026-04-25 12:00      1

-- billing snapshot (NULL — cash payment, no card billing address)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery requested — Karen provided home address at counter)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Karen                White               karen@example.com  555-100-0003    456 Oak Avenue          NULL                    Dallas         TX              75201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
5   4      PROD-A  Widget Pro    SN-030  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
4   4      Service Fee   30.00
```

**Grand total**
```
subtotal $200 + fees $30 + shipping $20 + tax $0 = $250 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
5   4         order         4           cash    250.00  paid    2026-04-23 08:00
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome          issue_description            unit_received_at      examined_by  examination_notes                       closed_at   closed_by  created_by  withdrawn_at          withdrawn_by
2   CMP-2026-002  4      5     SN-030  closed  no_fault_found      returned_to_customer  Screen flickering, unusable  2026-05-01 14:00      3            Unit fully functional, no defect found  2026-05-02 15:00  1          4  NULL                  NULL
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
4   order           4             outbound   FedEx    FX-10004   8.50        delivered  2026-04-23 09:00  NULL                  2026-04-25 12:00
13  complaint       2             inbound    FedEx    FX-20002   7.00        delivered  2026-04-30 00:00  NULL                  2026-05-01 14:00
17  complaint       2             outbound   FedEx    FX-30002   8.50        delivered  2026-05-02 14:00  NULL                  2026-05-04 12:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-030  sold    NULL      with Karen White
```

**`inventory_movements`**
```
id   serial  type       from            to              reference      notes
5    SN-030  sale       Warehouse A     NULL            ORD-2026-004
18   SN-030  return_in  NULL            Receiving Area  CMP-2026-002   returned by customer
--   SN-030  transfer   Receiving Area  Tech Area       CMP-2026-002   moved for examination
--   SN-030  transfer   Tech Area       Shipping Area   CMP-2026-002   no fault, prepping return
34   SN-030  adjustment Shipping Area   NULL            CMP-2026-002   returned to customer, no fault
```

`--` = intermediate IDs sit between id=18 and id=34 in global ledger (other orders' movements in between).

---

### Financial Summary
```
charged:   $250.00
collected: $250.00
refunded:  $0.00
net:       $250.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount — charged to Karen)
cost:     $24.00  (shipments: $8.50 outbound + $7.00 inbound complaint + $8.50 return)
margin:   -$4.00  (absorbed — no-fault complaint handling cost)
```

---

## Example 4 — ORD-005 — Multi-Line, Concurrent Complaints

**Scenario:** David Park walks in. Pays with Stripe Terminal (card-present). Buys 3 items. Asks for home delivery. Two items fail shortly after — both complaints run in parallel:
- **CMP-003** (SN-040, Widget Pro) — device dead. Admin sends replacement immediately (Flow B). Old unit arrives 7 days later, examined, internal fault, scrapped. Free replacement kept.
- **CMP-004** (SN-041, Widget Basic) — overheating. Customer ships unit back first (Flow A). Examined, no fault found, returned to David. No charge.
- **SN-042** (Widget Mini) — no issues throughout.

---

### Data Flow

```
[David walks in — admin creates order at POS]
        │
        ├──→ orders (customer_id=4, billing NULL (terminal, card-present), shipping snapshot filled)
        ├──→ order_lines (3 line items: SN-040, SN-041, SN-042)
        └──→ order_fees (service fee $50)

[David taps card on Stripe Terminal — instant]
        │
        └──→ payments INSERT (status=paid, stripe_terminal_reader_id)
             orders.payment_status → paid
             orders.status → processing

[Ali (Warehouse) packs and ships all 3 items together]
        │
        └──→ shipments INSERT #5 (order/5/outbound, FX-10005)
             inventory_movements INSERT #6 (SN-040 sale)
             inventory_movements INSERT #7 (SN-041 sale)
             inventory_movements INSERT #8 (SN-042 sale)
             inventory_serials: SN-040, SN-041, SN-042 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-26 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[David calls — SN-040 dead]
        │
        └──→ CMP-2026-003 created (order_line=6, serial=SN-040)
             Admin decides: send replacement immediately (Flow B)

[David calls — SN-041 overheating]
        │
        └──→ CMP-2026-004 created (order_line=7, serial=SN-041)
             Admin decides: customer ships unit back first (Flow A)

── PARALLEL ─────────────────────────────────────────────────────────────

CMP-2026-003 Flow B (replacement first):     CMP-2026-004 Flow A (examine first):
  REP-002 created (free, SN-043)               Raj sends David prepaid return label
  shipments #25: SN-043 → David               David ships SN-041 (May 1)
  inventory_movements #22: SN-043             shipments #14: inbound (FX-20004)
    replacement_out                            SN-041 status → expected_return
  SN-043 status → assigned (in transit)
  SN-040 status → expected_return             [SN-041 arrives May 3]
                                               inventory_movements #19: SN-041 return_in
  [SN-043 delivered May 3]                    SN-041 → under_examination
                                               CMP-2026-004.status → in_progress
  REP-002 status → delivered                  Sam (Tech) examines → no_fault_found
  SN-043 → sold (with David)                 inventory_movements --: transfer to Shipping Area
                                              inventory_movements #35: adjustment (handed back)
  [SN-040 arrives May 8]                     SN-041 → sold (returned to David)
  shipments #19: inbound (UP-20003)          shipments #18: outbound return (FX-30004)
  inventory_movements #27: SN-040 return_in  CMP-2026-004 closed: no_fault_found,
  SN-040 → under_examination                              returned_to_customer
  CMP-2026-003.status → in_progress
  Sam (Tech) examines → internal_issues
  inventory_movements --: transfer to Tech Area
  inventory_movements --: adjustment (scrapped)
  SN-040 → scrapped
  CMP-2026-003 closed: internal_issues, scrapped, free

─────────────────────────────────────────────────────────────────────────

[All complaints resolved — 2026-05-08]
```

---

### Schema + Data

**`customers`**
```
id  name        email              phone         status
4   David Park  david@example.com  555-100-0004  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
4   4            Home   David       Park       david@example.com  555-100-0004  789 Oak Ave    Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
5   ORD-2026-005  4            walk_in  shipped  paid            430.00    50.00  30.00     510.00       2026-04-24 11:00      2           2026-04-26 15:00      1

-- billing snapshot (NULL — stripe_terminal, card-present, no manual billing entry at POS)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at POS)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
David                Park               david@example.com  555-100-0004    789 Oak Ave             Dallas         TX              75201                 US
```

stripe_terminal = card-present. Terminal reads the chip/tap — no manual billing address entry. Billing snapshot NULL.

**`order_lines`**
```
id  order  sku     product_name   serial  unit_price  tax_rate  tax_amount  line_total
6   5      PROD-A  Widget Pro     SN-040  200.00      0.0000    0.00        200.00
7   5      PROD-B  Widget Basic   SN-041  150.00      0.0000    0.00        150.00
8   5      PROD-C  Widget Mini    SN-042   80.00      0.0000    0.00         80.00
```

AvaTax calculates `tax_rate` per line (product tax code + shipping destination). `tax_amount` = unit_price × tax_rate. Zero in fixture data — AvaTax not engaged.

**`order_fees`**
```
id  order  name          amount
5   5      Service Fee   50.00
```

**Grand total**
```
subtotal $430 (200+150+80) + fees $50 + shipping $30 + tax $0 = $510 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
6   5         order         5           stripe_terminal  510.00  paid    tmr_xxx                    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
5   order           5             outbound   FedEx    FX-10005   14.00       delivered  2026-04-24 11:00  NULL                  2026-04-26 15:00
14  complaint       4             inbound    FedEx    FX-20004    7.00       delivered  2026-05-01 00:00  NULL                  2026-05-03 09:00
18  complaint       4             outbound   FedEx    FX-30004    8.50       delivered  2026-05-03 14:00  NULL                  2026-05-06 11:00
19  complaint       3             inbound    UPS      UP-20003    0.00       delivered  2026-05-06 00:00  NULL                  2026-05-08 11:00
25  replacement     2             outbound   FedEx    FX-40002    8.50       delivered  2026-05-01 14:00  NULL                  2026-05-03 12:00
```

- id=5: original order delivery (3 items together, $14 label)
- id=14, 18: CMP-2026-004 (SN-041, Flow A) — David ships to us, we ship back. id=14 label_cost=$7 prepaid label we sent David.
- id=19: CMP-2026-003 (SN-040, Flow B) — old unit arrives 7 days after replacement shipped. label_cost=$0 David used own label.
- id=25: REP-002 — SN-043 replacement shipped to David same day CMP-2026-003 reported.

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome          issue_description               unit_received_at     examined_by  examination_notes                            closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
3   CMP-2026-003  5      6     SN-040  closed  internal_issues     scrapped              Device dead, needs unit now     2026-05-08 11:00     3            Confirmed dead — internal component failure  2026-05-08 12:00     1          4  NULL                  NULL
4   CMP-2026-004  5      7     SN-041  closed  no_fault_found      returned_to_customer  Widget Basic overheating badly  2026-05-03 09:00     3            No defect found, unit fully functional       2026-05-03 15:00     1          4  NULL                  NULL
```

Both created ~2026-04-29 (shortly after delivery). Run in parallel with different protocols.
CMP-2026-003 unit_received_at = May 8 — old unit arrives well after replacement already delivered (Flow B).
CMP-2026-004 unit_received_at = May 3 — David ships promptly for examination (Flow A).

**`replacements`**
```
id  number         order  parent  complaint  type  charge  pay_status  status
2   REP-2026-002   5      NULL    3          free  NULL    NULL        delivered
```

Free — internal fault confirmed. Company absorbs cost. No charge to David.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
2   2    6           PROD-A  Widget Pro    SN-040      SN-043
```

**`notes`**
```
id  order_id  body                                              created_by  created_at
1   5         Customer walked in — 3 items, terminal payment    4           2026-04-24 11:00
2   5         CMP-003: SN-040 dead on arrival, Flow B started   4           2026-04-29 09:00
3   5         CMP-004: SN-041 overheating badly, Flow A started 4           2026-04-29 09:30
4   5         REP-002: SN-043 replacement shipped to David      1           2026-05-01 14:00
5   5         CMP-004 closed — no fault, SN-041 returned        1           2026-05-03 15:00
6   5         CMP-003 closed — internal fault confirmed, SN-040 scrapped    1           2026-05-08 12:00
```

All 6 notes share `order_id=5`. Single `WHERE order_id = 5` returns full chain history.

**`inventory_serials`**
```
serial  status    location  note
SN-040  scrapped  NULL      CMP-2026-003 — internal fault confirmed, written off
SN-041  sold      NULL      with David Park — no fault (Flow A), returned to customer
SN-042  sold      NULL      with David Park — no issues ✓
SN-043  sold      NULL      with David Park — REP-002 free replacement
```

**`inventory_movements`**

CMP-003 (SN-040, Flow B — replacement first, old unit examined 7 days later):
```
id   serial  type             from          to            reference      notes
6    SN-040  sale             Warehouse A   NULL          ORD-2026-005
22   SN-043  replacement_out  Warehouse A   NULL          REP-2026-002   replacement shipped immediately
27   SN-040  return_in        NULL            Receiving Area  CMP-2026-003   old unit arrives 7 days later — logged at dock, unit_received_at set
--   SN-040  transfer         Receiving Area  Tech Area       CMP-2026-003   warehouse staff moves unit to technician
--   SN-040  adjustment       Tech Area     NULL          CMP-2026-003   internal fault confirmed, scrapped
```

`--` after id=27: intermediate IDs sit between id=27 and next global entries (other orders between them).

CMP-004 (SN-041, Flow A — examine first, no fault, returned):
```
id   serial  type       from          to             reference      notes
7    SN-041  sale       Warehouse A   NULL           ORD-2026-005
19   SN-041  return_in  NULL            Receiving Area  CMP-2026-004   David ships unit in — logged at dock, unit_received_at set
--   SN-041  transfer   Receiving Area  Tech Area       CMP-2026-004   warehouse staff moves unit to technician
--   SN-041  transfer   Tech Area       Shipping Area   CMP-2026-004   no fault, prepping return to customer
35   SN-041  adjustment Shipping Area NULL           CMP-2026-004   no fault, handed back to David
```

`--` between id=19 and id=35: other orders' movements sit in between in global ledger.

SN-042 (no complaint):
```
id  serial  type  from         to    reference
8   SN-042  sale  Warehouse A  NULL  ORD-2026-005
```

---

### Financial Summary
```
charged:   $510.00   (1 payment row — REP-002 is free, no separate charge)
collected: $510.00
refunded:  $0.00
net:       $510.00 ✓
```

### Shipping Margin
```
revenue:  $30.00  (orders.shipping_amount — charged to David)
cost:     $38.00  (id=5 $14 + id=14 $7 + id=18 $8.50 + id=19 $0 + id=25 $8.50)
margin:   -$8.00  (absorbed — two concurrent complaints, 5 shipment legs)
```

---

## Example 5 — ORD-006 — Flow B: No Fault, Charged Replacement

**Scenario:** Lisa Chen orders online. Pays via Stripe card. Widget Pro SN-050 reported as not turning on. Admin sends replacement immediately (Flow B). SN-051 delivered to Lisa. SN-050 arrives 8 days later — examined, no fault found. Lisa charged $80 for the replacement. SN-050 goes back to warehouse stock.

Key distinction from Example 4 CMP-2026-003: both Flow B, but David's was `internal_issues` → free. Lisa's is `no_fault_found` → charged. Customer pays when the unit is fine.

---

### Data Flow

```
[Lisa orders online]
        │
        ├──→ orders (customer_id=5, billing + shipping snapshot filled)
        ├──→ order_lines (1 line: SN-050)
        └──→ order_fees (service fee $20)

[Lisa pays via Stripe card — sync]
        │
        └──→ payments INSERT #7 (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Ali (Warehouse) ships]
        │
        └──→ shipments INSERT #6 (order/6/outbound, FX-10006)
             inventory_movements INSERT #9 (SN-050 sale)
             SN-050 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-27 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Lisa calls — SN-050 not turning on]
        │
        └──→ CMP-2026-005 created (order_line=9, serial=SN-050)
             Admin decides: send replacement immediately (Flow B)

[Admin sends replacement — same day]
        │
        ├──→ REP-2026-003 created (type=charged, complaint_id=5)
        ├──→ shipments INSERT #26 (replacement/3/outbound, FX-40003)
        └──→ inventory_movements INSERT #23 (SN-051 replacement_out)
             SN-051 → assigned (in transit to Lisa)
             SN-050 → expected_return (old unit, with Lisa)

[SN-051 delivered to Lisa 2026-05-03]
        │
        └──→ REP-2026-003.status → delivered
             SN-051 → sold (with Lisa)

[SN-050 arrives back 2026-05-09 — 8 days after replacement shipped]
        │
        ├──→ shipments #20: inbound (complaint/5, FX-20005, prepaid label)
        ├──→ inventory_movements INSERT #28 (SN-050 return_in)
        │    SN-050 → under_examination
        │    CMP-2026-005.status → in_progress
        │
        └──→ Sam (Tech) examines → no_fault_found
             inventory_movements -- (transfer → Tech Area)
             inventory_movements -- (adjustment → Warehouse A, back to stock)
             SN-050 → in_stock (Warehouse A)

[No fault confirmed — admin charges Lisa for replacement]
        │
        └──→ payments INSERT #8 (payable=replacement/3, stripe_card, $80, paid)
             REP-2026-003.pay_status → paid
             CMP-2026-005.status → closed
```

---

### Schema + Data

**`customers`**
```
id  name       email             phone         status
5   Lisa Chen  lisa@example.com  555-100-0005  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1  city     state  postal_code  country  is_default
5   5            Home   Lisa        Chen       lisa@example.com  555-100-0005  321 Elm St     Houston  TX     77001        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
6   ORD-2026-006  5            online  shipped  paid            200.00    20.00  20.00     240.00       2026-04-25 09:00      2           2026-04-27 14:00      1

-- billing snapshot (stripe_card, card-not-present → billing filled)
billing_first_name  billing_last_name  billing_email     billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Lisa                Chen               lisa@example.com  555-100-0005   321 Elm St             Houston       TX             77001                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Lisa                 Chen                lisa@example.com  555-100-0005    321 Elm St              Houston        TX              77001                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
9   6      PROD-A  Widget Pro    SN-050  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
6   6      Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
7   6         order         6           stripe_card  240.00  paid    pi_ord_xxx                ch_ord_xxx
8   6         replacement   3           stripe_card   80.00  paid    pi_rep_xxx                ch_rep_xxx
```

Two payments on same order. id=7 upfront for order. id=8 after-the-fact for replacement — triggered when examination confirms no fault.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
6   order           6             outbound   FedEx    FX-10006    8.50       delivered  2026-04-25 09:00  NULL                  2026-04-27 14:00
20  complaint       5             inbound    FedEx    FX-20005    7.00       delivered  2026-05-07 00:00  NULL                  2026-05-09 10:00
26  replacement     3             outbound   FedEx    FX-40003    8.50       delivered  2026-05-01 11:00  NULL                  2026-05-03 14:00
```

- id=6: original order delivery
- id=20: CMP-2026-005 — SN-050 arrives 8 days after replacement shipped. label_cost=$7 prepaid label we sent Lisa.
- id=26: REP-2026-003 — SN-051 shipped to Lisa immediately (Flow B, May 1)

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome   issue_description             unit_received_at     examined_by  examination_notes                          closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
5   CMP-2026-005  6      9     SN-050  closed  no_fault_found      back_to_stock  Device not turning on at all  2026-05-09 10:00     3            Unit fully functional — no defect detected  2026-05-09 12:00     1          4  NULL                  NULL
```

Flow B — replacement shipped May 1, SN-050 arrives 8 days later May 9. No fault → charged.
`back_to_stock` outcome: SN-050 returned to Warehouse A inventory, not sent back to customer (Lisa already has SN-051).

**`replacements`**
```
id  number         order  parent  complaint  type     charge  pay_status  status
3   REP-2026-003   6      NULL    5          charged  80.00   paid        delivered
```

Charged — no fault confirmed = customer responsible. Payment collected after examination, not upfront.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
3   3    9           PROD-A  Widget Pro    SN-050      SN-051
```

**`inventory_serials`**
```
serial  status    location     note
SN-050  in_stock  Warehouse A  CMP-2026-005 — no fault, back to stock
SN-051  sold      NULL         with Lisa Chen — REP-2026-003
```

**`inventory_movements`**
```
id   serial  type             from          to            reference      notes
9    SN-050  sale             Warehouse A   NULL          ORD-2026-006
23   SN-051  replacement_out  Warehouse A   NULL          REP-2026-003   replacement shipped immediately (Flow B)
28   SN-050  return_in        NULL            Receiving Area  CMP-2026-005   old unit arrives 8 days later — logged at dock, unit_received_at set
--   SN-050  transfer         Receiving Area  Tech Area       CMP-2026-005   warehouse staff moves unit to technician
--   SN-050  adjustment       Tech Area       Warehouse A     CMP-2026-005   no fault, back to stock
```

`--` after id=28: intermediate IDs in global ledger.
`to=Warehouse A` on adjustment — back_to_stock. Compare: `to=NULL` means item left the system (returned to customer or scrapped).

---

### Financial Summary
```
charged:   $320.00   (2 payment rows — payment #7 $240 order + payment #8 $80 replacement)
collected: $320.00
refunded:  $0.00
net:       $320.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $24.00  (id=6 $8.50 + id=20 $7.00 + id=26 $8.50)
margin:   -$4.00  (absorbed — complaint handling overhead)
```

---

## Example 6 — ORD-007 — Flow A: Damaged by Customer, Charged Replacement

**What's new vs all previous examples:**

| | Karen (Ex 3) | Lisa (Ex 5) | Emma (Ex 6) |
|--|--|--|--|
| Flow | A | B | A |
| Result | no_fault_found | no_fault_found | **damaged_by_customer** |
| Replacement | none | charged after exam | **charged after exam** |
| Refund | none | none | **none — customer's fault** |
| Old unit | returned_to_customer | back_to_stock | **scrapped** |
| Warranty | valid | valid | **voided** |

**Scenario:** Emma Davis walks in. Pays Stripe Terminal. Widget Pro SN-060 reported with grinding noise. Customer ships unit back (Flow A). Sam examines → physical damage found — unit dropped, screen cracked. Customer's fault — warranty voided, no free replacement. Admin offers replacement at full price ($80). Emma agrees, pays. SN-061 shipped. SN-060 scrapped.

---

### Data Flow

```
[Emma walks in — admin creates order at POS]
        │
        ├──→ orders (customer_id=6, billing NULL (terminal), shipping snapshot filled)
        ├──→ order_lines (1 line: SN-060)
        └──→ order_fees (service fee $20)

[Emma taps card on Stripe Terminal — instant]
        │
        └──→ payments INSERT #9 (status=paid, stripe_terminal_reader_id)
             orders.payment_status → paid
             orders.status → processing

[Ali ships]
        │
        └──→ shipments INSERT #7 (order/7/outbound, FX-10007)
             inventory_movements INSERT #10 (SN-060 sale)
             SN-060 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-28 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Emma calls — SN-060 grinding noise]
        │
        └──→ CMP-2026-006 created (order_line=10, serial=SN-060)
             Admin: customer ships unit back first (Flow A)

[Emma ships SN-060 back — her own label]
        │
        └──→ shipments #15: inbound (complaint/6, UP-20006, label_cost=0)
             SN-060 → expected_return

[SN-060 arrives 2026-05-04]
        │
        ├──→ inventory_movements INSERT #20 (SN-060 return_in)
        │    SN-060 → under_examination
        │    CMP-2026-006.status → in_progress
        │
        └──→ Sam (Tech) examines → damaged_by_customer
             Physical damage — unit dropped, screen cracked
             Warranty voided — no free replacement
             inventory_movements -- (transfer → Tech Area)
             inventory_movements -- (adjustment → scrapped)
             SN-060 → scrapped

[Admin informs Emma — damage confirmed, offers replacement at $80]
        │
        └──→ Emma agrees → REP-2026-004 created (type=charged, $80)
             payments INSERT #10 (payable=replacement/4, stripe_terminal, $80, paid)
             REP-2026-004.pay_status → paid

[Replacement SN-061 shipped 2026-05-05]
        │
        ├──→ shipments INSERT #27 (replacement/4/outbound, FX-40004)
        └──→ inventory_movements INSERT #32 (SN-061 replacement_out)
             SN-061 → assigned (in transit)

[SN-061 delivered 2026-05-07]
        │
        └──→ REP-2026-004.status → delivered
             SN-061 → sold (with Emma)
             CMP-2026-006.status → closed
```

---

### Schema + Data

**`customers`**
```
id  name        email             phone         status
6   Emma Davis  emma@example.com  555-100-0006  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1  city         state  postal_code  country  is_default
6   6            Home   Emma        Davis      emma@example.com  555-100-0006  567 Pine Ave   San Antonio  TX     78201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
7   ORD-2026-007  6            walk_in  shipped  paid            200.00    20.00  20.00     240.00       2026-04-26 10:00      2           2026-04-28 11:00      1

-- billing snapshot (NULL — stripe_terminal, card-present, no manual billing entry)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at POS)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Emma                 Davis               emma@example.com  555-100-0006    567 Pine Ave            San Antonio    TX              78201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
10  7      PROD-A  Widget Pro    SN-060  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
7   7      Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
9   7         order         7           stripe_terminal  240.00  paid    tmr_xxx                    pi_ord_xxx                ch_ord_xxx
10  7         replacement   4           stripe_terminal   80.00  paid    tmr_xxx                    pi_rep_xxx                ch_rep_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
7   order           7             outbound   FedEx    FX-10007    8.50       delivered  2026-04-26 10:00  NULL                  2026-04-28 11:00
15  complaint       6             inbound    UPS      UP-20006    0.00       delivered  2026-05-03 00:00  NULL                  2026-05-04 11:00
27  replacement     4             outbound   FedEx    FX-40004    8.50       delivered  2026-05-05 10:00  NULL                  2026-05-07 12:00
```

id=15 `label_cost=0` — Emma used her own return label.

**`complaints`**
```
id  number        order  line  serial  status  examination_result   unit_outcome  issue_description           unit_received_at     examined_by  examination_notes                              closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
6   CMP-2026-006  7      10    SN-060  closed  damaged_by_customer  scrapped      Motor making grinding noise  2026-05-04 11:00    3            Physical damage — unit dropped, screen cracked  2026-05-07 15:00     1          4  NULL                  NULL
```

`damaged_by_customer` — warranty voided. No free replacement. Customer pays full price.

**`replacements`**
```
id  number         order  parent  complaint  type     charge  pay_status  status
4   REP-2026-004   7      NULL    6          charged  80.00   paid        delivered
```

Full charge — no discount, no refund. Customer's fault confirmed by examination.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
4   4    10          PROD-A  Widget Pro    SN-060      SN-061
```

**`inventory_serials`**
```
serial  status    location  note
SN-060  scrapped  NULL      CMP-2026-006 — damaged_by_customer, warranty voided, scrapped
SN-061  sold      NULL      with Emma Davis — REP-2026-004
```

**`inventory_movements`**
```
id   serial  type             from          to            reference      notes
10   SN-060  sale             Warehouse A   NULL          ORD-2026-007
20   SN-060  return_in        NULL            Receiving Area  CMP-2026-006   Emma ships back, her own label — logged at dock, unit_received_at set
--   SN-060  transfer         Receiving Area  Tech Area       CMP-2026-006   warehouse staff moves unit to technician
--   SN-060  adjustment       Tech Area     NULL          CMP-2026-006   damaged_by_customer, scrapped
32   SN-061  replacement_out  Warehouse A   NULL          REP-2026-004   replacement ships after damage confirmed
```

`--` between id=20 and id=32: intermediate IDs in global ledger.
`to=NULL` on SN-060 adjustment = scrapped permanently.

---

### Financial Summary
```
charged:   $320.00   (2 payment rows — payment #9 $240 order + payment #10 $80 replacement)
collected: $320.00
refunded:  $0.00     (no refund — customer's fault)
net:       $320.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $17.00  (id=7 $8.50 + id=15 $0 + id=27 $8.50)
margin:   +$3.00  (Emma used own label — saved $7 vs prepaid)
```

---

## Example 7 — ORD-009 — Post-Delivery Return, Full Refund

**Post-delivery return policy:**
```
Customer requests refund
→ Admin: "Send items back first"
→ Items arrive → inspection

Good condition → full refund processed
Damaged / signs of use → 20–30% restocking fee deducted, remainder refunded

Refund amount is 100% manual admin decision — no auto-calculation enforced.
Admin enters any amount based on reason, condition, and judgment.
```

**Scenario:** Amanda Taylor orders online. Pays Stripe card. Both items delivered Apr 30. Amanda contacts support — items not as described. Admin asks her to ship items back first. Both arrive May 4 — inspection confirms good condition. Full $330 refunded. Both serials back to stock. No complaints created — this is a return, not a defect report.

---

### Data Flow

```
[Amanda orders online]
        │
        ├──→ orders (customer_id=7, billing + shipping snapshot filled)
        ├──→ order_lines (2 lines: SN-080, SN-081)
        └──→ order_fees (service fee $30)

[Amanda pays via Stripe card — sync]
        │
        └──→ payments INSERT #12 (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Ali ships both items together]
        │
        └──→ shipments INSERT #9 (order/9/outbound, FX-10009)
             inventory_movements INSERT #13 (SN-080 sale)
             inventory_movements INSERT #14 (SN-081 sale)
             SN-080, SN-081 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-30 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Amanda contacts support — items not as described]
        │
        └──→ Admin: "Please ship items back, we will inspect and process refund"
             No refund yet — items must arrive first

[Amanda ships both items back — her own label]
        │
        └──→ shipments INSERT #32 (order/9/inbound, UP-50009, label_cost=0)
             SN-080, SN-081 status → expected_return

[Both items arrive 2026-05-04 — inspection passes]
        │
        ├──→ inventory_movements INSERT #36 (SN-080 return_in)
        ├──→ inventory_movements INSERT #37 (SN-081 return_in)
        │    SN-080, SN-081 → in_stock (Warehouse A)
        │
        └──→ Inspection: good condition — admin enters full refund amount
             refunds INSERT REF-005 ($330, stripe, processed)
             orders.status → refunded, cancelled_at = 2026-05-04, cancelled_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name           email               phone         status
7   Amanda Taylor  amanda@example.com  555-100-0007  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email               phone         address_line1  city     state  postal_code  country  is_default
7   7            Home   Amanda      Taylor     amanda@example.com  555-100-0007  890 Maple Dr   Phoenix  AZ     85001        US       true
```

**`orders`**
```
id  number        customer_id  source  status     payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by  cancelled_at         cancelled_by
9   ORD-2026-009  7            online  refunded   paid            300.00    30.00  0.00      330.00       2026-04-28 10:00      2           2026-04-30 12:00      1             2026-05-04 12:00     1

-- billing snapshot (stripe_card, card-not-present → billing filled)
billing_first_name  billing_last_name  billing_email       billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Amanda              Taylor             amanda@example.com  555-100-0007   890 Maple Dr           Phoenix       AZ             85001                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Amanda               Taylor              amanda@example.com  555-100-0007    890 Maple Dr            Phoenix        AZ              85001                 US
```

`cancelled_at=2026-05-04` — reused as refund event timestamp. Set after inspection confirms good condition, not at request time.
`payment_status=paid` stays — refund tracked in `refunds` table separately.
`shipping=0.00` — free shipping, nothing to refund on shipping.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
13  9      PROD-A  Widget Pro    SN-080  200.00      0.0000    0.00        200.00
14  9      PROD-B  Widget Basic  SN-081  100.00      0.0000    0.00        100.00
```

**`order_fees`**
```
id  order  name          amount
9   9      Service Fee   30.00
```

**Grand total**
```
subtotal $300 (200+100) + fees $30 + shipping $0 + tax $0 = $330 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
12  9         order         9           stripe_card  330.00  paid    pi_xxx                    ch_xxx
```

**`refunds`**
```
id  number   order  type   payable  amount  ship_refund  method  reason                                                status
5   REF-005  9         order  9        330.00  0.00         stripe  Post-delivery return — inspection passed, full refund  processed
```

Refund issued after inspection, not at request time.
`amount=330.00` — admin entered full amount: good condition confirmed, no restocking fee.
`ship_refund=0.00` — shipping was $0, nothing to refund.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
9   order           9             outbound   FedEx    FX-10009   10.00       delivered  2026-04-28 10:00  NULL                  2026-04-30 12:00
32  order           9             inbound    UPS      UP-50009    0.00       delivered  2026-05-02 00:00  NULL                  2026-05-04 10:00
```

Both `shippable_type=order` — no complaint, no replacement involved.
id=32 `label_cost=0` — Amanda used her own return label.

**`inventory_serials`**
```
serial  status    location     note
SN-080  in_stock  Warehouse A  ORD-2026-009 — return inspected, good condition, back to stock
SN-081  in_stock  Warehouse A  ORD-2026-009 — return inspected, good condition, back to stock
```

**`inventory_movements`**
```
id   serial  type       from            to              reference      notes
13   SN-080  sale       Warehouse A     NULL            ORD-2026-009
14   SN-081  sale       Warehouse A     NULL            ORD-2026-009
36   SN-080  return_in  NULL            Receiving Area  ORD-2026-009   package arrives at dock — visual inspection at receiving
37   SN-081  return_in  NULL            Receiving Area  ORD-2026-009   package arrives at dock — visual inspection at receiving
--   SN-080  transfer   Receiving Area  Warehouse A     ORD-2026-009   good condition confirmed, back to stock
--   SN-081  transfer   Receiving Area  Warehouse A     ORD-2026-009   good condition confirmed, back to stock
```

Visual check happens at Receiving Area (not Tech Area) — no technician involved, warehouse staff inspect condition at dock.
Transfer → Warehouse A once condition confirmed. No Tech Area step for order-level returns.
Reference = `ORD-2026-009` (no complaint number) — return is against the order directly.

---

### Financial Summary
```
charged:   $330.00   (1 payment row)
collected: $330.00
refunded:  $330.00   (REF-005 — inspection passed, admin entered full amount)
net:       $0.00     (fully reversed)
```

### Shipping Margin
```
revenue:  $0.00   (shipping_amount=0 — free shipping)
cost:     $10.00  (id=9 outbound $10 + id=32 $0 Amanda's own label)
margin:   -$10.00 (absorbed)
```

---

## Example 8 — ORD-010 — Flow B: Unit Never Returned, Open Case

**Scenario:** Chris Martinez walks in. Pays cash. Admin ships SN-090 to his home via FedEx. Item delivered May 1 — Chris calls same day, reports device malfunctioning after delivery. Admin sends replacement (SN-091) — Flow B, trust customer. Chris ships SN-090 back May 5 (his own label) but package never arrived — tracking shows in transit, delivered_at NULL. 35 days later: open case, no examination, no closure.

**What's new vs all previous examples:**
- Cash + walk_in + delivery (billing NULL, shipping filled)
- Complaint filed after delivery — complaint still open, status=`in_progress`, no `closed_at`
- Shipment inbound has `delivered_at=NULL` ⚠️ — package never arrived after 35 days
- No `examination_result`, no `unit_outcome` — nothing examined, unit never received
- Order status stays `shipped` — no `closed_at` on order (complaint unresolved)
- Admin has 3 options still pending at day 35

---

### Data Flow

```
[Chris walks in — buys at counter]
        │
        ├──→ orders (customer_id=8, billing NULL (cash), shipping snapshot filled)
        ├──→ order_lines (1 line: SN-090)
        └──→ order_fees (service fee $20)

[Chris pays cash at counter]
        │
        └──→ payments INSERT #13 (cash, status=paid, cash_received_at=2026-04-29 09:00)
             orders.payment_status → paid
             orders.status → processing

[Ali ships SN-090 via FedEx]
        │
        └──→ shipments INSERT #10 (order/10/outbound, FX-10010)
             inventory_movements INSERT #15 (SN-090 sale, Warehouse A → NULL)
             SN-090 → sold
             orders.status → shipped, shipped_at=2026-04-29 09:00, shipped_by=Ali (user_id=2)

[Delivered 2026-05-01 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at=2026-05-01 14:00, delivered_by=1
             (orders.status stays shipped — no status change)

[Chris calls May 1 — reports device malfunctioning after delivery]
        │
        └──→ Admin decides: send replacement immediately (Flow B — trust customer)
             CMP-2026-009 created (order_line=15, serial=SN-090, status=open, created_by=4)
             REP-2026-007 created (free, complaint_id=9)
             shipments INSERT #30 (replacement/7/outbound, FX-40007)
             inventory_movements INSERT #25 (SN-091 replacement_out, Warehouse A → NULL)
             SN-091 → assigned (in transit to Chris)
             SN-090 → expected_return
             CMP-2026-009.status → in_progress

[SN-091 delivered to Chris 2026-05-03]
        │
        └──→ REP-2026-007.status → delivered
             SN-091 → sold (with Chris)

[Chris ships SN-090 back 2026-05-05 — his own label]
        │
        └──→ shipments #22: inbound (complaint/9, UP-20009, label_cost=0)
             tracking created — package dropped off by Chris

[35 days pass — SN-090 never arrives]
        │
        └──→ shipments #22: delivered_at = NULL ⚠️
             SN-090: status=expected_return, location=NULL — 35 days with no update ⚠️
             CMP-2026-009: still in_progress — no examination possible, no closure

[Admin options at day 35 — unresolved]
        ├── Option A: Write off → SN-090.status = missing, complaint closed (absorb loss)
        ├── Option B: Charge Chris → REP-007 type=charged, INSERT payment ($200 unit cost)
        └── Option C: Escalate → contact Chris, 7-day final notice before charging
```

---

### Schema + Data

**`customers`**
```
id  name            email              phone         status
8   Chris Martinez  chris@example.com  555-100-0008  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1   city       state  postal_code  country  is_default
8   8            Home   Chris       Martinez   chris@example.com  555-100-0008  123 Cedar Blvd  Las Vegas  NV     89101        US       true
```

**`orders`**
```
id  number        customer_id  source   status     payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
10  ORD-2026-010  8            walk_in  shipped    paid            200.00    20.00  20.00     240.00       2026-04-29 09:00      2           2026-05-01 14:00      1

-- billing snapshot (NULL — cash payment, no billing address)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at counter)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Chris                Martinez            chris@example.com  555-100-0008    123 Cedar Blvd          Las Vegas      NV              89101                 US
```

Complaint still open — order stays `shipped`. Orders have no `closed` status.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
15  10     PROD-A  Widget Pro    SN-090  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
10  10     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
13  10        order         10          cash    240.00  paid    2026-04-29 09:00
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
10  order           10            outbound   FedEx    FX-10010    8.50       delivered   2026-04-29 09:00  NULL                  2026-05-01 14:00
22  complaint       9             inbound    UPS      UP-20009    0.00       in_transit  2026-05-05 00:00  NULL                  NULL            ⚠️
30  replacement     7             outbound   FedEx    FX-40007    8.50       delivered   2026-05-01 16:00  NULL                  2026-05-03 12:00
```

- id=10: original order delivery
- id=22: SN-090 return — `delivered_at=NULL` — package never arrived after 35 days ⚠️. label_cost=0 — Chris used his own label.
- id=30: REP-2026-007 — SN-091 shipped to Chris immediately (Flow B)

**`complaints`**
```
id  number        order  line  serial  status           examination_result  unit_outcome  issue_description              unit_received_at  examined_by  examination_notes  closed_at  closed_by  created_by  withdrawn_at          withdrawn_by
9   CMP-2026-009  10     15    SN-090  in_progress      NULL                NULL          Device malfunctioning, urgent  NULL              NULL         NULL               NULL       NULL       4  NULL                  NULL
```

All NULL after `status` — unit never arrived, no examination possible. Case still open.
`in_progress` = replacement shipped, waiting for old unit — stuck here 35 days.

**`replacements`**
```
id  number         order  parent  complaint  type  charge  pay_status  status
7   REP-2026-007   10     NULL    9          free  NULL    NULL        delivered
```

Started free (Flow B trust). May change to `charged` if admin picks Option B at day 35.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
7   7    15          PROD-A  Widget Pro    SN-090      SN-091
```

**`inventory_serials`**
```
serial  status           location  note
SN-090  expected_return  NULL      CMP-2026-009 — 35 days overdue, never arrived ⚠️⚠️
SN-091  sold             NULL      with Chris Martinez — REP-2026-007
```

`expected_return` — status set when REP-007 shipped. Never updated because unit never arrived.

**`inventory_movements`**
```
id   serial  type             from          to    reference      notes
15   SN-090  sale             Warehouse A   NULL  ORD-2026-010
25   SN-091  replacement_out  Warehouse A   NULL  REP-2026-007   Flow B — immediate replacement
```

No `return_in` for SN-090 — package never arrived. Ledger stops here for this chain.

---

### Financial Summary
```
charged:   $240.00   (1 payment row — REP-007 free, no separate charge)
collected: $240.00
refunded:  $0.00
net:       $240.00

⚠️ Unresolved: SN-090 ($200 unit) unaccounted — financial risk pending admin decision
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $17.00  (id=10 $8.50 + id=22 $0 Chris's label + id=30 $8.50)
margin:   +$3.00
```

---

## Example 9 — ORD-011 — Phone Order, In-Store Pickup, In-Person Complaint

**Scenario:** Tom Wilson calls the store. Admin creates order on his behalf. Tom comes in next day, pays Stripe Terminal, picks up Widget Pro SN-100. Two days later returns — device not powering on. Hands unit at counter. Sam examines — internal fault. Free replacement SN-101 handed to Tom same day. No shipping at any point.

**What's new vs all previous examples:**
- `source=phone` — admin creates on behalf of customer who called
- `orders.status → complete` — in-store pickup, no carrier
- No shipments table — no labels, no carrier, customer walks in/out
- Complaint handled entirely at counter — unit handed over in person

---

### Data Flow

```
[Tom calls — admin creates order on his behalf]
        │
        ├──→ orders (customer_id=9, source=phone, billing NULL (terminal), shipping NULL (pickup), status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line: SN-100)
        └──→ order_fees (service fee $20)

[Tom arrives next day — taps Stripe Terminal, picks up at counter]
        │
        └──→ payments INSERT #14 (stripe_terminal, status=paid)
             orders.payment_status → paid
             orders.status → processing
             inventory_movements INSERT #38 (sale, Warehouse A → NULL)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → complete

[Tom returns 2 days later — device not powering on, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-010 (order_line=16, serial=SN-100, status=open, created_by=4)
             inventory_movements INSERT #39 (return_in, NULL → Tech Area)
             inventory_serials UPDATE (sold → under_examination)
             complaints.unit_received_at = 2026-05-10 11:00
             complaints.status → in_progress

[Sam examines same day — internal fault confirmed]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-100 (under_examination → scrapped)

[Free replacement SN-101 handed to Tom at counter]
        │
        └──→ REP-2026-008 created (type=free, complaint_id=10)
             inventory_movements INSERT #40 (replacement_out, Warehouse A → NULL)
             inventory_serials UPDATE SN-101 (in_stock → sold)
             REP-2026-008.status → delivered
             complaints.unit_outcome → scrapped
             complaints.status → closed, closed_at = 2026-05-10 15:00, closed_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name        email            phone         status
9   Tom Wilson  tom@example.com  555-100-0009  active
```

**`customer_addresses`**
```
-- no rows for Tom — phone order, in-store pickup, no address collected
```

**`orders`**
```
id  number        customer_id  source  status    payment_status  subtotal  fees   shipping  grand_total  shipped_at  shipped_by
11  ORD-2026-011  9            phone   complete  paid            200.00    20.00  0.00      220.00       NULL        NULL

-- billing snapshot (NULL — stripe_terminal, card-present)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (NULL — in-store pickup, no address needed)
shipping_first_name  shipping_last_name  shipping_email  shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
NULL                 NULL                NULL            NULL            NULL                    NULL           NULL            NULL                  NULL
```

`shipped_at=NULL`, `shipped_by=NULL` — in-store pickup, no carrier involved.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
16  11     PROD-A  Widget Pro    SN-100  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
11  11     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $0 + tax $0 = $220 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
14  11        order         11          stripe_terminal  220.00  paid    tmr_xxx                    pi_xxx                    ch_xxx
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome  issue_description       unit_received_at     examined_by  examination_notes                     closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
10  CMP-2026-010  11     16    SN-100  closed  internal_issues     scrapped      Device not powering on  2026-05-10 11:00     3            Internal component failure confirmed  2026-05-10 15:00     1          4  NULL                  NULL
```

**`replacements`**
```
id  number        order  parent  complaint  type  charge  pay_status  status
8   REP-2026-008  11     NULL    10         free  NULL    NULL        delivered
```

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
8   8    16          PROD-A  Widget Pro    SN-100      SN-101
```

**`inventory_serials`**
```
serial  status    location  note
SN-100  scrapped  NULL      CMP-2026-010 — internal fault confirmed, scrapped
SN-101  sold      NULL      with Tom Wilson — REP-2026-008
```

**`inventory_movements`**
```
id   serial  type             from         to          reference      notes
38   SN-100  sale             Warehouse A  NULL        ORD-2026-011   picked up at counter
39   SN-100  return_in        NULL         Tech Area   CMP-2026-010   Tom hands unit at counter
--   SN-100  adjustment       Tech Area    NULL        CMP-2026-010   internal fault, scrapped
40   SN-101  replacement_out  Warehouse A  NULL        REP-2026-008   handed to Tom at counter
```

No shipments — in-store pickup, in-person return, in-store replacement. Zero carrier involvement.

---

### Financial Summary
```
charged:   $220.00
collected: $220.00
refunded:  $0.00
net:       $220.00 ✓
```

### Shipping Margin
```
revenue:  $0.00
cost:     $0.00  (no shipments)
margin:   $0.00
```

---

## Example 10 — ORD-012 — Phone Order, In-Store Pickup, Chained Complaint, Full Refund

**Scenario:** Jane Kim calls the store. Admin creates order. Jane comes in next day, pays cash, picks up Widget Pro SN-110. Three days later SN-110 fails — Jane returns to counter. Internal fault confirmed. Free replacement SN-111 handed at counter (REP-009). Three days later SN-111 also fails. Jane returns again. Internal fault again. Admin decides: two consecutive faults = refund, no second replacement. Full $220 cash refund.

**What's new vs Example 9:**
- Two complaints, same order — second complaint serial is replacement unit (SN-111), not original (SN-110)
- `complaints.order_line=17` stays same across both complaints — replacement serial never creates new order line
- `replacements.parent` shown — NULL on REP-009 (first). If second replacement issued, it would set `parent=9`
- `orders.status → refunded` — admin policy: two faults = refund over second replacement
- Cash payment → cash refund

---

### Data Flow

```
[Jane calls — admin creates order]
        │
        ├──→ orders (customer_id=10, source=phone, billing NULL (cash), shipping NULL (pickup), status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line: SN-110)
        └──→ order_fees (service fee $20)

[Jane arrives next day — pays cash at counter, picks up SN-110]
        │
        └──→ payments INSERT #15 (cash, status=paid, cash_received_at=2026-05-11 10:00)
             orders.payment_status → paid
             orders.status → processing
             inventory_movements INSERT #41 (sale, Warehouse A → NULL)
             inventory_serials UPDATE SN-110 (in_stock → sold)
             orders.status → complete

[Jane returns 3 days later — SN-110 not working, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-011 (order_line=17, serial=SN-110, status=open, created_by=4)
             inventory_movements INSERT #42 (return_in, NULL → Tech Area)
             inventory_serials UPDATE SN-110 (sold → under_examination)
             complaints.unit_received_at = 2026-05-13 10:00
             complaints.status → in_progress

[Sam examines — internal fault confirmed]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-110 (under_examination → scrapped)

[Free replacement SN-111 handed to Jane at counter]
        │
        └──→ REP-2026-009 created (type=free, complaint_id=11)
             inventory_movements INSERT #43 (replacement_out, Warehouse A → NULL)
             inventory_serials UPDATE SN-111 (in_stock → sold)
             REP-2026-009.status → delivered
             CMP-2026-011.unit_outcome → scrapped
             CMP-2026-011.status → closed, closed_at = 2026-05-13 14:00, closed_by = 1

[Jane returns 3 days later — SN-111 also not working, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-012 (order_line=17, serial=SN-111, status=open, created_by=4)
             ⚠️ serial=SN-111 (replacement unit) — same order_line=17 (original line, no new line created)
             inventory_movements INSERT #44 (return_in, NULL → Tech Area)
             inventory_serials UPDATE SN-111 (sold → under_examination)
             complaints.unit_received_at = 2026-05-16 11:00
             complaints.status → in_progress

[Sam examines — internal fault confirmed again]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-111 (under_examination → scrapped)

[Admin decides: two faults = refund, no second replacement]
        │
        └──→ refunds INSERT REF-006 ($220, cash, processed)
             CMP-2026-012.unit_outcome → scrapped
             CMP-2026-012.status → closed, closed_at = 2026-05-16 15:00, closed_by = 1
             orders.status → refunded
```

---

### Schema + Data

**`customers`**
```
id  name      email             phone         status
10  Jane Kim  jane@example.com  555-100-0010  active
```

**`customer_addresses`**
```
-- no rows for Jane — phone order, in-store pickup, no address collected
```

**`orders`**
```
id  number        customer_id  source  status    payment_status  subtotal  fees   shipping  grand_total  shipped_at  shipped_by  cancelled_at         cancelled_by
12  ORD-2026-012  10           phone   refunded  paid            200.00    20.00  0.00      220.00       NULL        NULL        2026-05-16 15:00     1

-- billing snapshot (NULL — cash payment)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (NULL — in-store pickup)
shipping_first_name  shipping_last_name  shipping_email  shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
NULL                 NULL                NULL            NULL            NULL                    NULL           NULL            NULL                  NULL
```

`shipped_at=NULL`, `shipped_by=NULL` — pickup, no carrier.
`cancelled_at` reused as terminal timestamp for refund event — same column serves both `cancelled` and `refunded` final states.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
17  12     PROD-A  Widget Pro    SN-110  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
12  12     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $0 + tax $0 = $220 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
15  12        order         12          cash    220.00  paid    2026-05-11 10:00
```

**`refunds`**
```
id  number   order  type   payable  amount  ship_refund  method  reason                                                         status
6   REF-006  12        complaint  12       220.00  0.00         cash    Two consecutive internal faults — refund over second replacement  processed
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome  issue_description             unit_received_at     examined_by  examination_notes                      closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
11  CMP-2026-011  12     17    SN-110  closed  internal_issues     scrapped      Widget Pro not working        2026-05-13 10:00     3            Internal component failure confirmed   2026-05-13 14:00     1          4  NULL                  NULL
12  CMP-2026-012  12     17    SN-111  closed  internal_issues     scrapped      Replacement also not working  2026-05-16 11:00     3            Internal fault — second occurrence     2026-05-16 15:00     1          4  NULL                  NULL
```

CMP-2026-012 `serial=SN-111` — replacement unit, not original. `order_line=17` same across both complaints — no new line created for replacement units.

**`replacements`**
```
id  number        order  parent  complaint  type  charge  pay_status  status
9   REP-2026-009  12     NULL    11         free  NULL    NULL        delivered
```

`parent=NULL` — first replacement in chain.

**Sub-case: Chained replacement (replacement unit itself fails, second replacement issued)**

If admin had issued a second replacement instead of refund, the `replacements` table would show:

```
id  number        order  parent  complaint  type  charge  pay_status  status
9   REP-2026-009  12     NULL    11         free  NULL    NULL        delivered
10  REP-2026-010  12     9       12         free  NULL    NULL        delivered
```

And `replacement_lines`:
```
id  rep  order_line  sku     product_name  old_serial  new_serial
9   9    17          PROD-A  Widget Pro    SN-110      SN-111
10  10   17          PROD-A  Widget Pro    SN-111      SN-112
```

Rules:
- `parent=9` on REP-010 — links to the replacement it supersedes
- `order_line=17` stays same across entire chain — always the original purchase line
- Each replacement's `old_serial` = previous replacement's `new_serial`
- Chain depth unlimited — follow `parent` FK to trace full history
- SN-112 would be `sold` (with customer). SN-111 `scrapped` (second fault confirmed).

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
9   9    17          PROD-A  Widget Pro    SN-110      SN-111
```

**`inventory_serials`**
```
serial  status    location  note
SN-110  scrapped  NULL      CMP-2026-011 — internal fault, scrapped
SN-111  scrapped  NULL      CMP-2026-012 — internal fault again, scrapped — refund issued
```

**`inventory_movements`**
```
id   serial  type             from         to          reference      notes
41   SN-110  sale             Warehouse A  NULL        ORD-2026-012   picked up at counter
42   SN-110  return_in        NULL         Tech Area   CMP-2026-011   Jane hands SN-110 at counter
--   SN-110  adjustment       Tech Area    NULL        CMP-2026-011   internal fault, scrapped
43   SN-111  replacement_out  Warehouse A  NULL        REP-2026-009   handed to Jane at counter
44   SN-111  return_in        NULL         Tech Area   CMP-2026-012   Jane returns SN-111 — second failure
--   SN-111  adjustment       Tech Area    NULL        CMP-2026-012   internal fault again, scrapped
```

No shipments — all in-store.

---

### Financial Summary
```
charged:   $220.00   (1 payment row)
collected: $220.00
refunded:  $220.00   (REF-006 — two faults, full cash refund)
net:       $0.00     (fully reversed)
```

### Shipping Margin
```
revenue:  $0.00
cost:     $0.00  (no shipments)
margin:   $0.00
```

---

## Serial Assignment Rule — Oversell Prevention

### Schema constraint (required)
Unique constraint on `order_lines.serial_number_id`.
DB rejects duplicate assignment at write time — silent data corruption impossible.

### Business rule
Only `in_stock` serials can be assigned to an order line.

| Serial status      | Assignable | Reason |
|--------------------|-----------|--------|
| in_stock           | ✓ yes     | available |
| sold               | ✗ no      | belongs to another order |
| assigned           | ✗ no      | in transit for replacement |
| expected_return    | ✗ no      | awaiting customer return |
| under_examination  | ✗ no      | being examined by tech |
| scrapped           | ✗ no      | written off |
| missing            | ✗ no      | unaccounted |

### Implementation notes (for agent)
- Wrap serial assignment + status update in a single DB transaction
- Use `lockForUpdate()` on the serial row before checking status — prevents
  race condition where two concurrent requests grab the same serial
- Throw exception if status ≠ in_stock before assigning
- Unique constraint is the DB-level safety net; app-level lock is the
  race-condition guard — both are required, neither alone is sufficient

---

## Warehouse Damage Write-off Rule

Unit damaged in warehouse before sale. No customer, no complaint, no order involved.

### Data pattern
```
inventory_movements:
id   serial  type        from         to    reference  notes
--   SN-XXX  adjustment  Warehouse A  NULL  NULL       warehouse damage write-off — [reason]

inventory_serials:
serial  status    location  note
SN-XXX  scrapped  NULL      warehouse damage — written off [date]
```

### Rules
- `type=adjustment` — same movement type used for all inventory removals
- `to=NULL` — unit permanently removed from system
- `reference=NULL` — no order, complaint, or replacement linked
- `inventory_serials.status → scrapped` — same as complaint-scrapped units

### Reporting write-offs
```sql
-- All unlinked adjustments = warehouse write-offs (no complaint, no order)
SELECT * FROM inventory_movements
WHERE type = 'adjustment'
AND reference IS NULL;
```

Financial impact: unit cost absorbed as a loss at write-off date.

---

## Global Reference — Payments

> All payment data lives in the `payments` table (polymorphic — covers orders and replacements).
> All monetary amounts are **USD**. Schema includes `currency char(3) DEFAULT 'USD'` on `orders`, `payments`, and `refunds`.

### Source → method rule

| `orders.source` | Allowed `payments.method` values |
|-----------------|----------------------------------|
| `online` | `stripe_card` only |
| `walk_in` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |
| `phone` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |

`stripe_checkout` = CSR generates a Stripe-hosted checkout link / QR code. Customer pays on their own device. Used in-house (walk_in / phone) — not the same as online card.

### Billing snapshot rule

| `payments.method` | Billing snapshot | Reason |
|-------------------|-----------------|--------|
| `stripe_card` | **Required** — copied from `customer_addresses` | Stripe requires billing address for card-not-present |
| `stripe_terminal` | NULL | Card-present — chip/tap, no manual billing entry |
| `cash` | NULL | No card, no billing required |
| `stripe_checkout` | NULL | Customer pays on Stripe-hosted page, no billing snapshot stored in our DB |
| `cheque` | NULL | No card, no billing required |

### Shipping snapshot rule

| Order type | Shipping snapshot |
|------------|-----------------|
| Carrier delivery (FedEx, UPS, etc.) | **Required** — both NULL not allowed if a shipment row exists |
| In-store pickup | NULL allowed — no shipment row, `orders.status = complete` |

### Method → columns filled

| `payments.method` | Stripe-specific columns | Other columns | `payments.status` flow |
|-------------------|------------------------|---------------|----------------------|
| `stripe_card` | `stripe_payment_intent_id`, `stripe_charge_id` | — | `paid` (sync at checkout) |
| `stripe_terminal` | `stripe_terminal_reader_id`, `stripe_payment_intent_id`, `stripe_charge_id` | — | `paid` (sync at tap/chip) |
| `cash` | — | `cash_received_at` | `paid` (admin marks on receipt) |
| `stripe_checkout` | `stripe_checkout_session_id` | — | `pending` → `paid` (webhook on payment), `expired` if session times out |
| `cheque` | — | `cheque_number`, `cheque_date` | `pending` → `paid` (admin marks when cheque clears) |

> `stripe_terminal` stores all three Stripe columns: `stripe_terminal_reader_id` (which device processed it), `stripe_payment_intent_id` + `stripe_charge_id` (Stripe Dashboard lookup + refund API).

> **Agent rule:** never read `orders.payment_status = paid` without also checking `payments.status`. For stripe_checkout and cheque, `payments.status = pending` while awaiting payment/clearance. The order won't advance to `processing` until `payments.status = paid`.

> **Audit:** `payments.created_by` (FK → `users`) — admin who initiated the payment. Always set. Stripe webhooks update `payments.status` but do not change `created_by` — it reflects who created the payment row.
> `payments.paid_at` (timestamp, nullable) + `payments.paid_by` (FK → `users`, nullable) — when/who confirmed pending → paid. Set only for `cheque` (admin marks cleared) and `stripe_checkout` (webhook sets paid_at only; paid_by NULL). NULL for instant-paid methods (stripe_card, stripe_terminal, cash) — use `created_at` for those.

---

## Global Reference — Shipments

### `shipments.status`

| Value | Meaning | Timestamp set |
|-------|---------|---------------|
| `pending` | Label generated, not yet in carrier network | — |
| `in_transit` | Carrier scanned, package in network | `shipped_at` |
| `delivered` | Delivered — admin records manually for carrier orders | `delivered_at` |
| `returned` | Carrier returned package to sender after failed delivery | `returned_at` |
| `voided` | Label cancelled before use — label cost not recoverable | — |

### Timestamp + audit rules

| Column | When set |
|--------|---------|
| `shipped_at` | Carrier activates label (first scan) |
| `returned_at` | Package physically arrives back at warehouse |
| `delivered_at` | Admin records on delivery confirmation |
| `delivered_by` | FK → `users` — admin who recorded delivery. NULL for MVP (carrier confirms via tracking); future: integrate carrier webhook or manual admin marking |

> All three timestamps default NULL. `in_transit` → sets `shipped_at`. `returned` → sets `returned_at`. `delivered` → sets `delivered_at` + `delivered_by`. `voided` and `pending` → all remain NULL.

### Direction values

| Value | Meaning |
|-------|---------|
| `outbound` | Warehouse → customer (orders, replacements) |
| `inbound` | Customer → warehouse (complaint returns, order returns) |

> In-store pickup orders have no shipment row — `orders.status = complete` is the delivery signal.

> **Audit:** `shipments.created_by` (FK → `users`) — warehouse staff who generated the label. Always set.
> `shipments.delivered_by` (FK → `users`, nullable) — admin who recorded delivery. NULL in MVP (carrier confirms via tracking); future: integrate carrier webhook or manual marking.

---

## Global Reference — Refunds

> Refunds are created manually by admin after inspection or decision. Never auto-created by the system.

> **Audit:** `refunds.created_by` (FK → `users`) — admin who initiated the refund. Always set.
> `refunds.processed_by` (FK → `users`, nullable) — admin who completed the refund. `refunds.processed_at` (timestamp, nullable) — when refund was marked processed. Both NULL until status transitions from `pending` → `processed`.
> `created_by` and `processed_by` may be the same user (small team) or different (initiator/approver separation for compliance).

### Column aliases
| Example shows | Actual DB column | Notes |
|---------------|-----------------|-------|
| `order` | `order_id` (FK → `orders`) | Always populated — the originating order |
| `payable` | `payable_id` | Paired with `type` (polymorphic) |

### `refunds.type` + `refunds.payable` (polymorphic)
| `type` | `payable` points to | When used |
|--------|--------------------|-----------| 
| `order` | `orders.id` | Direct return — no complaint, customer returns order |
| `complaint` | `complaints.id` | Complaint escalated to refund after examination |

### `refunds.status`
| Value | Meaning |
|-------|---------|
| `pending` | Refund initiated, not yet processed (Stripe async) |
| `processed` | Refund complete — money returned to customer |

### `refunds.method`
Matches the original `payments.method` — refund goes back via same channel.

### `refunds.amount` vs `refunds.ship_refund`
- `amount` — product refund (may be less than grand_total if restocking fee applied)
- `ship_refund` — shipping cost refunded separately (`0.00` if free shipping or shipping not refunded)
- Total refund = `amount + ship_refund`

---

## Global Reference — Notes

All free-text notes across the entire order lifecycle live in one table.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | FK → `orders` | Always set — every note ties back to originating order |
| `body` | text | Free-text content |
| `created_by` | FK → `users` | Staff member who wrote the note |
| `created_at` | timestamp | When written |

> Query pattern: `WHERE order_id = ?` returns all notes for an order and everything under it (complaints, replacements, refunds, shipments). One query = full order history.

---

## Global Reference — Replacements

> Replacement data lives in the `replacements` table. Each replacement links to the complaint that triggered it.

### Replacement creation is always manual

Tech sets `examination_result` only — no automatic replacement is triggered. Admin reviews the result and decides: replace, refund, or return unit. Only then creates the replacement row manually.

| Role | Action |
|------|--------|
| Technician | Sets `examination_result` (internal_issues / damaged_by_customer / no_fault_found) |
| Admin/CSR | Reviews result → decides outcome → manually creates replacement row or processes refund |

> **Agent rule:** never auto-create a replacement on examination result. Always wait for explicit admin action. The `replacements.created_by` column will always be an admin/CSR user_id — never a system process.

### Replacement status timestamps

| Column | Set when | Actor |
|--------|---------|-------|
| `shipped_at` | Replacement leaves warehouse | `shipped_by` (FK → `users` — warehouse staff) |
| `delivered_at` | Admin records customer receipt | `delivered_by` (FK → `users` — admin/CSR) |

Both default NULL — set only when status transitions to `shipped` / `delivered`.

---

### `replacements.parent_id`

| Value | Meaning |
|-------|---------|
| `NULL` | First replacement for this complaint chain |
| `<replacements.id>` | Second or later replacement — points to previous replacement in the chain |

Set when admin issues a replacement for a unit that **was itself a replacement**. Forms a linked list: REP-001 → REP-002 (`parent_id=1`) → REP-003 (`parent_id=2`).

---

### Chain rules

- `complaints.order_line_id` stays the **same** across all complaints in a chain — replacement units never create a new order line
- Each replacement = a new serial (`in_stock` unit assigned). Old serial goes through the full complaint flow: `return_in → examine → outcome`
- No hard DB limit on chain depth — business policy decides when to stop. Ex 10 shows admin choosing refund at second fault rather than issuing a second replacement
- `parent_id` is set on the `replacements` row only — not on the complaint row. Trace the chain via `replacements` joins

### Chain query
```sql
-- Full replacement chain for an order line
SELECT r.id, r.number, r.parent_id, rl.new_serial_id, r.status, r.created_at
FROM replacements r
JOIN replacement_lines rl ON rl.replacement_id = r.id
WHERE rl.order_line_id = :order_line_id
ORDER BY r.created_at ASC;
```

---

### Payment rule per replacement

| Fault type | Charge? |
|-----------|---------|
| `internal_issues` (first occurrence) | Free — warranty |
| `internal_issues` (second occurrence) | Business decision — Ex 10: refund chosen over second replacement |
| `damaged_by_customer` | Charged — customer responsible |

---

### Serial lifecycle per chain link

```
New serial (in_stock)
    → replacement_out movement (Warehouse A → NULL)
    → serial status: in_stock → assigned → sold (if shipped to customer)
      Counter handoff (in-person): no transit — assigned skipped → in_stock → sold directly
    │
    ├── If fails again:
    │       complaint opened on replacement serial
    │       serial: sold → expected_return → under_examination
    │       admin decides: new replacement (parent_id set) OR refund
    │
    └── If no further issue:
            serial stays sold — chain ends
```

> See Ex 10 (ORD-012) for a worked chain ending in refund. `replacements.parent_id` is NULL in Ex 10 — second replacement never issued, admin chose refund instead.

---

## Example 11 — ORD-013 — Walk-in + Stripe Checkout

**Scenario:** Diana Walsh walks into the store. Buys one Widget Pro. Doesn't have cash, doesn't have a physical card — CSR generates a Stripe checkout QR link. Diana scans it on her phone and pays online. Admin ships via FedEx to Diana's home address. No issues.

> **Why this matters:** `source=walk_in` + `method=stripe_checkout`. Billing snapshot is NULL (not card-not-present in our system — Stripe handles the billing on their hosted page). Shipping filled — delivery requested.

---

### Data Flow

```
[Customer walks in — admin creates order]
        │
        ├──→ customer_addresses INSERT (Diana provides home address)
        ├──→ orders (customer_id=11, billing NULL — checkout, shipping snapshot filled, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_fees (service fee)

[CSR generates Stripe checkout link — Diana scans QR on phone]
        │
        └──→ payments INSERT (status=pending, stripe_checkout_session_id=cs_xxx)
             (order stays pending until webhook fires)

[Stripe webhook fires — payment confirmed]
        │
        └──→ payments.status → paid
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id   name         email               phone         status
11   Diana Walsh  diana@example.com   555-100-0011  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1    address_line2  city     state  postal_code  country  is_default
11   11           Home   Diana       Walsh      diana@example.com   555-100-0011  789 Pine Street  NULL           Dallas   TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
13  ORD-2026-013  11           walk_in  shipped  paid            200.00    20.00  15.00     235.00       2026-05-19 11:00      2           2026-05-21 14:00      1

-- billing snapshot (NULL — stripe_checkout, Stripe handles billing on their hosted page)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses — delivery requested)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Diana                Walsh               diana@example.com   555-100-0011    789 Pine Street         NULL                    Dallas         TX              75201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
40  13     PROD-A  Widget Pro    SN-120   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
20  13     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $15 + tax $0 = $235 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_checkout_session_id
16  13        order         13          stripe_checkout  235.00  paid    cs_xxx
```

> `payments.status` transitions: `pending` (session open, waiting for Diana to pay) → `paid` (Stripe webhook fires on successful payment). If Diana never pays and session expires: `expired` — order stays `pending`, CSR generates new link or takes alternate payment.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
34  order           13            outbound   FedEx    FX-10013    8.50        delivered  2026-05-19 11:00  NULL                  2026-05-21 14:00
```

**`inventory_serials`**
```
serial   status  location  note
SN-120   sold    NULL      with Diana Walsh
```

**`inventory_movements`**
```
id  serial   type  from         to    reference     notes
45  SN-120   sale  Warehouse A  NULL  ORD-2026-013
```

---

### Financial Summary
```
charged:   $235.00
collected: $235.00
refunded:  $0.00
net:       $235.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $8.50   (shipments.label_cost)
margin:   +$6.50
```

---

## Example 12 — ORD-014 — Phone Order + Cheque

**Scenario:** Robert Kim calls in. Buys one Widget Basic ($150). Offers to pay by company cheque — gives cheque number and date by phone. CSR records order + cheque details (`status=pending`). Cheque arrives and clears next day. Admin marks payment received, ships via UPS. No issues.

> **Why this matters:** `source=phone` + `method=cheque`. Two-step payment: cheque recorded first (`pending`), order ships only after admin confirms cheque cleared (`paid`). Billing NULL. Shipping filled — phone order with home delivery.

---

### Data Flow

```
[Customer calls — admin creates order + records cheque]
        │
        ├──→ customer_addresses INSERT (Robert gives home address by phone)
        ├──→ orders (customer_id=12, billing NULL — cheque, shipping snapshot filled, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        ├──→ order_fees (service fee)
        └──→ payments INSERT (status=pending, cheque_number, cheque_date)
             (order stays pending — not shipped until cheque clears)

[Cheque arrives + clears — admin confirms]
        │
        └──→ payments.status → paid
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id   name        email              phone         status
12   Robert Kim  robert@example.com  555-100-0012  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1   address_line2  city       state  postal_code  country  is_default
12   12           Home   Robert      Kim        robert@example.com  555-100-0012  321 Elm Street  NULL           Phoenix    AZ     85001        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
14  ORD-2026-014  12           phone   shipped  paid            150.00    15.00  15.00     180.00       2026-05-21 10:00      2           2026-05-23 15:00      1

-- billing snapshot (NULL — cheque payment, no card billing required)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses — phone order, home delivery)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Robert               Kim                 robert@example.com  555-100-0012    321 Elm Street          NULL                    Phoenix        AZ              85001                 US
```

**`order_lines`**
```
id  order  sku     product_name   serial   unit_price  tax_rate  tax_amount  line_total
41  14     PROD-B  Widget Basic   SN-130   150.00      0.0000    0.00        150.00
```

**`order_fees`**
```
id  order  name          amount
21  14     Service Fee   15.00
```

**Grand total**
```
subtotal $150 + fees $15 + shipping $15 + tax $0 = $180 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cheque_number  cheque_date
17  14        order         14          cheque  180.00  paid    CHQ-5500       2026-05-20
```

> `payments.status` transitions: `pending` (cheque recorded at call, not yet cleared) → `paid` (admin confirms cheque cleared, order advances to `processing`). Do NOT ship while `payments.status = pending` — cheque may bounce.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
35  order           14            outbound   UPS      UP-10014    9.00        delivered  2026-05-21 10:00  NULL                  2026-05-23 15:00
```

**`inventory_serials`**
```
serial   status  location  note
SN-130   sold    NULL      with Robert Kim
```

**`inventory_movements`**
```
id  serial   type  from         to    reference     notes
46  SN-130   sale  Warehouse A  NULL  ORD-2026-014
```

---

### Financial Summary
```
charged:   $180.00
collected: $180.00
refunded:  $0.00
net:       $180.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $9.00   (shipments.label_cost)
margin:   +$6.00
```

---

## Example 13 — ORD-015 — Withdrawn Complaint

**Scenario:** Linda Green buys one item online. Pays via Stripe card. Delivered. Same day calls in — device won't turn on. CSR opens complaint CMP-2026-013, serial → `expected_return`. CSR generates prepaid UPS return label ($7.00 paid upfront). Next morning Linda calls back — device started working after full charge overnight, wants to withdraw. CSR records withdrawal. Complaint closes as `withdrawn`. Label voided — cost already paid, no refund from carrier. Serial reverts `expected_return → sold`.

> **Key rule:** `withdrawn` only valid while complaint is `open` — no `return_in` movement yet. Once carrier scans the package (`status → in_progress`), withdrawal is impossible.

> **Prepaid label cost rule:** label generated = cost paid to carrier immediately. Voiding does not recover the cost. Always absorbed as a loss regardless of withdrawal. Distinguish from customer-paid labels (`label_cost=0.00`) which are only tracked once the carrier scans the package.

---

### Data Flow

```
[Admin creates order — Linda pays online]
        │
        ├──→ orders (customer_id=13, billing + shipping filled, status=pending)
        ├──→ order_lines (1 line item, SN-140)
        ├──→ order_fees (service fee)
        └──→ payments INSERT #18 (stripe_card, status=paid)
             orders.payment_status → paid, orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT #36 (outbound, FedEx FX-10015)
             inventory_movements INSERT #47 (sale, SN-140 Warehouse A → NULL)
             inventory_serials UPDATE: in_stock → sold
             orders.status → shipped, shipped_at, shipped_by set

[Delivered — admin records — 2026-05-28 09:00]
        │
        └──→ orders.delivered_at, delivered_by set

[Linda calls — device won't turn on — 2026-05-28 10:00]
        │
        └──→ complaints INSERT CMP-2026-013 (status=open, created_by=4)
             inventory_serials UPDATE: sold → expected_return
             shipments INSERT #37 (inbound, status=pending, label_cost=7.00)
             ← label generated + paid upfront at this point

[Linda calls next morning — device works, wants to withdraw — 2026-05-29 09:00]
        │
        └──→ complaints UPDATE: status → withdrawn
                                withdrawn_at = 2026-05-29 09:00
                                withdrawn_by = 4
             inventory_serials UPDATE: expected_return → sold   ← revert
             shipments UPDATE #37: status → voided
             ← label voided, $7.00 already paid — no refund from carrier
```

---

### Schema + Data

**`customers`**
```
id   name         email               phone         status
13   Linda Green  linda@example.com   555-100-0013  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1   city         state  postal_code  country  is_default
13   13           Home   Linda       Green      linda@example.com   555-100-0013  654 Cedar Lane  San Antonio  TX     78201        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
15  ORD-2026-015  13           online  shipped  paid            200.00    20.00  20.00     240.00       2026-05-26 10:00      2           2026-05-28 09:00      1

-- billing snapshot (filled — online stripe_card)
billing_first_name  billing_last_name  billing_email       billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Linda               Green              linda@example.com   555-100-0013   654 Cedar Lane         San Antonio   TX             78201                US

-- shipping snapshot (same address — delivery)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Linda                Green               linda@example.com   555-100-0013    654 Cedar Lane          San Antonio    TX              78201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
42  15     PROD-A  Widget Pro    SN-140   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
22  15     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
18  15        order         15          stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
36  order           15            outbound   FedEx    FX-10015    8.50        delivered  2026-05-26 10:00  NULL                  2026-05-28 09:00
37  complaint       13            inbound    UPS      UP-20013    7.00        voided     NULL              NULL              NULL
```

> Shipment #37: `status=pending` when label generated (cost paid). `status → voided` on withdrawal. `shipped_at=NULL` — Linda never dropped off the package. `label_cost=7.00` — paid to UPS, not recoverable.

> **Shipment status values for prepaid labels:**
> - `pending` — label generated and paid, waiting for customer to drop off
> - `voided` — label cancelled, cost already absorbed
> - `in_transit` — carrier scanned the package (label activated)
> - `delivered` — package arrived at warehouse

**`complaints`**
```
id   number         order  line  serial   status     examination_result  unit_outcome  issue_description       unit_received_at  examined_by  examination_notes  closed_at  closed_by  created_by  withdrawn_at          withdrawn_by
13   CMP-2026-013   15     42    SN-140   withdrawn  NULL                NULL          Device won't turn on    NULL              NULL         NULL               NULL       NULL       4           2026-05-29 09:00      4
```

> `examination_result=NULL, unit_outcome=NULL` — withdrawn before any examination. `closed_at=NULL` — `withdrawn` is its own terminal state, separate from `closed`. `unit_received_at=NULL` — unit never arrived.

**`inventory_serials`**
```
serial   status  location  note
SN-140   sold    NULL      with Linda Green
```

> Status timeline: `in_stock → sold` (sale) → `expected_return` (complaint opened) → `sold` (withdrawn — unit stays with Linda).

**`inventory_movements`**
```
id  serial   type  from         to    reference      notes
47  SN-140   sale  Warehouse A  NULL  ORD-2026-015
```

> One movement only. No `return_in`, no `adjustment` — unit never left Linda.

---

### Financial Summary
```
charged:   $240.00
collected: $240.00
refunded:  $0.00
net:       $240.00 ✓

label cost (voided prepaid):  -$7.00  (shipments.label_cost, status=voided — not recoverable)
net after label loss:         $233.00
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $8.50   (shipments #36 — outbound FX-10015, delivered)
margin:   +$11.50

voided return label:  -$7.00  (shipments #37 — prepaid label, never used, not recoverable)
net after label loss: +$4.50
```

---

## Example 14 — ORD-016 — Return to Sender, Re-shipped

**Scenario:** Marcus Rivera orders one item online, Stripe card, home delivery. Admin ships via FedEx (FX-10016). FedEx attempts delivery twice — apartment complex, no building access, no safe drop. Package returned to warehouse. `orders.status → rts`. Admin contacts Marcus — Marcus gives work address. Admin adds work address to `customer_addresses`, updates shipping snapshot, re-ships via FedEx (FX-10017). Delivered successfully. Re-ship label cost absorbed internally.

> **Serial stays `sold` throughout.** RTS is a logistics event — ownership did not change. No `return_in` movement, no serial status change.

> **`rts` is not terminal.** Order re-enters `shipped` after re-ship. Status flow: `processing → shipped → rts → shipped`.

> **Shipping snapshot vs billing snapshot:**
> - Billing snapshot — tied to the Stripe charge. Immutable. Never changes regardless of RTS or re-ship.
> - Shipping snapshot — operational ("where does this order ship to"). Updated by admin after RTS before re-ship. If left as old address, system pre-fills wrong address on new label — error-prone. Admin explicitly corrects it so shipment #39 label is accurate.
> - Audit trail preserved via shipment #38 (`status=returned`) + activity log. The RTS event is not lost.

---

### Data Flow

```
[Admin creates order — Marcus pays online]
        │
        ├──→ orders (customer_id=14, billing + shipping filled from home address, status=pending)
        ├──→ order_lines (1 line item, SN-150)
        ├──→ order_fees (service fee)
        └──→ payments INSERT #19 (stripe_card, status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid, orders.status → processing

[Admin ships — 2026-05-20]
        │
        └──→ shipments INSERT #38 (outbound, FedEx FX-10016, to home address)
             inventory_movements INSERT #48 (sale, SN-150 Warehouse A → NULL)
             inventory_serials UPDATE: in_stock → sold
             orders.status → shipped, shipped_at=2026-05-20 10:00, shipped_by=2

[FedEx attempts delivery twice — failed — 2026-05-22, 2026-05-23]
        │
        └──→ (carrier events — external, no DB changes)

[Package arrives back at warehouse — 2026-05-26]
        │
        └──→ shipments UPDATE #38: status → returned
             orders.status → rts
             (serial stays sold — RTS is not a return, no inventory movement)

[Admin contacts Marcus — Marcus gives work address — 2026-05-26]
        │
        └──→ customer_addresses INSERT id=15 (work address)
             (orders snapshot stays as home — original intent preserved, never changed)

[Admin re-ships — 2026-05-27]
        │
        └──→ shipments INSERT #39 (outbound, FedEx FX-10017, customer_address_id=15 — work)
             orders.status → shipped
             (no new inventory movement — serial already recorded as sold)

[Delivered — admin records — 2026-05-29]
        │
        └──→ orders.delivered_at=2026-05-29 14:00, delivered_by=1
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id   name           email                phone         status
14   Marcus Rivera  marcus@example.com   555-100-0014  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email                phone         address_line1      city    state  postal_code  country  is_default
14   14           Home   Marcus      Rivera     marcus@example.com   555-100-0014  200 Maple Ave #4B  Austin  TX     78704        US       true
15   14           Work   Marcus      Rivera     marcus@example.com   555-100-0014  900 Commerce St    Austin  TX     78701        US       false
```

> `id=14` (Home) — existed at order creation, used for billing + shipping snapshot (both immutable). `id=15` (Work) — added after RTS. Referenced via `shipments.customer_address_id=15` on re-ship — order snapshot not changed.

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
16  ORD-2026-016  14           online  shipped  paid            200.00    20.00  20.00     240.00       2026-05-20 10:00      2           2026-05-29 14:00      1

-- billing snapshot (home address — tied to Stripe charge, never changes)
billing_first_name  billing_last_name  billing_email        billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Marcus              Rivera             marcus@example.com   555-100-0014   200 Maple Ave #4B      Austin        TX             78704                US

-- shipping snapshot (home address — original intent at order creation, never changes)
shipping_first_name  shipping_last_name  shipping_email       shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Marcus               Rivera              marcus@example.com   555-100-0014    200 Maple Ave #4B       Austin         TX              78704                 US
```

> Billing and shipping snapshots both immutable — frozen at order creation. Actual delivery address per shipment attempt tracked via `shipments.customer_address_id` FK. `shipped_at` stays 2026-05-20 — records when admin first shipped. Re-ship date tracked via shipment #39's own `shipped_at`.

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
43  16     PROD-A  Widget Pro    SN-150   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
23  16     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
19  16        order         16          stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at      customer_address_id
38  order           16            outbound   FedEx    FX-10016    8.50        returned   2026-05-20 10:00      2026-05-24 14:00  NULL              14   ← home (failed attempt)
39  order           16            outbound   FedEx    FX-10017    8.50        delivered  2026-05-27 09:00  NULL                  2026-05-29 14:00  15   ← work (delivered)
```

> `customer_address_id` FK → `customer_addresses` — records exact address used for each shipment attempt. Shipment #38 → home (id=14, failed). Shipment #39 → work (id=15, delivered). Full audit trail without mutating `orders.shipping_snapshot`.
> Both shipments are `shippable_type=order, shippable_id=16` — same order, two attempts.

> **Shipment status `returned`:** package physically back at sender's warehouse after carrier failed all delivery attempts. Distinct from `voided` (label never used) and `in_transit` (in carrier network).

**`inventory_serials`**
```
serial   status  location  note
SN-150   sold    NULL      with Marcus Rivera
```

> Status never changes after sale. RTS does not affect ownership or serial status.

**`inventory_movements`**
```
id  serial   type  from         to    reference      notes
48  SN-150   sale  Warehouse A  NULL  ORD-2026-016
```

> One movement only. No second movement for re-ship — unit was already recorded as sold when first shipped. Physical transit back and forth is a carrier event, not an inventory event.

---

## Example 15 — ORD-017 — Walk-in Back Order (Prepaid Cash, Carrier)

**Scenario:** James Wilson walks in. Wants Widget Max (PROD-B) — out of stock. Admin creates back order. James pays full $245 cash upfront same visit. Stock arrives 5 days later via PO. Admin assigns serial SN-151 to the line. Order advances to processing. Admin ships via FedEx. Delivered.

---

### Data Flow

```
[Admin creates back order — James present at counter]
        │
        ├──→ orders INSERT (customer_id=15, source=walk_in, status=back_ordered, payment_status=unpaid,
        │                   billing=NULL — cash, shipping snapshot filled from address)
        ├──→ order_lines INSERT (inventory_serial_id=NULL, sku=PROD-B, unit_price=200.00)
        └──→ order_fees INSERT (Service Fee $20)

[James pays cash upfront — same visit]
        │
        └──→ payments INSERT (cash, amount=245.00, status=paid, cash_received_at=2026-05-14 11:00)
             orders.payment_status → paid
             (orders.status stays back_ordered — serial still NULL, stock not in)

[2026-05-19 — stock arrives via PO, new serial added to inventory]
        │
        └──→ inventory_serials INSERT (SN-151, in_stock, Warehouse A)
             inventory_movements INSERT (receive, NULL → Warehouse A, reference=PO-2026-010)

[Admin assigns SN-151 to back-ordered line]
        │
        └──→ order_lines.inventory_serial_id → SN-151
             payment_status=paid ✓ + all serials assigned ✓
             orders.status → processing

[Admin ships 2026-05-20]
        │
        └──→ shipments INSERT (order/17/outbound, FedEx FX-10017)
             inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-017)
             inventory_serials UPDATE (SN-151: in_stock → sold)
             orders.status → shipped
             orders.shipped_at = 2026-05-20 09:00, shipped_by = 2

[Delivered 2026-05-22 — admin records manually]
        │
        └──→ orders.delivered_at = 2026-05-22 14:00, delivered_by = 1
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id  name          email               phone         status
15  James Wilson  james@example.com   555-150-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
16  15           Home   James       Wilson     james@example.com  555-150-0001  88 Oak Ave     Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
17  ORD-2026-017  15           walk_in  shipped  paid            200.00    20.00  25.00     245.00       2026-05-20 09:00      2           2026-05-22 14:00      1

-- billing snapshot
NULL — cash payment, no billing snapshot required

-- shipping snapshot (filled at order creation)
shipping_first_name  shipping_last_name  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
James                Wilson              88 Oak Ave               Dallas         TX              75201                 US
```

One row in DB — split for readability.

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
44  17     PROD-B  Widget Max    SN-151   200.00      0.0000    0.00        200.00
```

> `serial` (inventory_serial_id) was NULL from 2026-05-14 (order creation) until 2026-05-19 (serial assigned after stock arrived). Shown here as final assigned state.

**`order_fees`**
```
id  order  name          amount
24  17     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $25 + tax $0 = $245 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at       created_by
20  17        order         17          cash    245.00  paid    2026-05-14 11:00       1
```

> Payment collected at order creation — same counter visit. `orders.status` was already `back_ordered` at creation — payment updates `payment_status` only.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            delivered_at          created_by
40  order           17            outbound   FedEx    FX-10017    9.00        delivered  2026-05-20 09:00      2026-05-22 14:00      2
```

**`inventory_serials`**
```
serial   status  location  note
SN-151   sold    NULL      with James Wilson — back order fulfilled 2026-05-20
```

**`inventory_movements`**
```
id  serial   type     from         to           reference      notes
49  SN-151   receive  NULL         Warehouse A  PO-2026-010    back order stock arrived
50  SN-151   sale     Warehouse A  NULL         ORD-2026-017
```

> Two movements for SN-151: `receive` when PO stock arrived (2026-05-19), `sale` when order shipped (2026-05-20). The receive movement references the PO, the sale references the order.

---

### Back Order State Timeline

```
2026-05-14  order created     status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-14  cash payment      status=back_ordered, payment_status=paid,    serial=NULL
2026-05-19  serial assigned   status=processing,   payment_status=paid,    serial=SN-151
2026-05-20  admin ships       status=shipped
2026-05-22  delivered         delivered_at set
```

---

### Financial Summary
```
charged:   $245.00
collected: $245.00  (cash — prepaid at order creation, 6 days before shipment)
refunded:  $0.00
net:       $245.00 ✓
```

### Shipping Margin
```
revenue:  $25.00  (orders.shipping)
cost:     $9.00   (shipments.label_cost)
margin:   +$16.00
```

---

## Example 16 — ORD-018 — Walk-in Back Order (Pay at Pickup, In-Store)

**Scenario:** Emma Clark walks in. Wants Widget Pro (PROD-A) — out of stock. Admin creates back order, no payment taken. Stock arrives 4 days later. Admin assigns serial SN-152, calls Emma. Emma comes in 2026-05-20, pays full $215 via Stripe Terminal at counter, takes unit home. No carrier involved.

---

### Data Flow

```
[Admin creates back order — Emma present at counter]
        │
        ├──→ orders INSERT (customer_id=16, source=walk_in, status=back_ordered, payment_status=unpaid,
        │                   billing=NULL, shipping=NULL — in-store pickup)
        ├──→ order_lines INSERT (inventory_serial_id=NULL, sku=PROD-A, unit_price=200.00)
        └──→ order_fees INSERT (Service Fee $15)

        (no payment taken — Emma will pay when she collects)

[2026-05-19 — stock arrives via PO]
        │
        └──→ inventory_serials INSERT (SN-152, in_stock, Warehouse A)
             inventory_movements INSERT (receive, NULL → Warehouse A, reference=PO-2026-011)

[Admin assigns SN-152 to back-ordered line, calls Emma]
        │
        └──→ order_lines.inventory_serial_id → SN-152
             payment_status=unpaid ✗ → orders.status stays back_ordered
             (serial assigned, waiting for Emma to come in and pay)

[2026-05-20 — Emma comes in, pays via Stripe Terminal]
        │
        └──→ payments INSERT (stripe_terminal, amount=215.00, status=paid)
             orders.payment_status → paid
             payment_status=paid ✓ + all serials assigned ✓
             orders.status → processing

[Emma takes unit at counter — in-store pickup]
        │
        └──→ inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-018)
             inventory_serials UPDATE (SN-152: in_stock → sold)
             orders.status → complete
             (no shipment row — in-store pickup)
```

> `processing → complete` are two distinct counter actions: payment recorded first (processing), then unit physically handed to Emma (complete + inventory movement).

---

### Schema + Data

**`customers`**
```
id  name        email              phone         status
16  Emma Clark  emma@example.com   555-160-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
17  16           Home   Emma        Clark      emma@example.com   555-160-0001  45 Pine St     Austin  TX     78702        US       true
```

**`orders`**
```
id  number        customer_id  source   status    payment_status  subtotal  fees   shipping  grand_total  created_by
18  ORD-2026-018  16           walk_in  complete  paid            200.00    15.00  0.00      215.00       1

-- billing + shipping snapshot: both NULL (Stripe Terminal card-present, in-store pickup)
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
45  18     PROD-A  Widget Pro    SN-152   200.00      0.0000    0.00        200.00
```

> `serial` (inventory_serial_id) was NULL from 2026-05-15 (order created) until 2026-05-19 (assigned after stock arrived). Shown as final state.

**`order_fees`**
```
id  order  name          amount
25  18     Service Fee   15.00
```

**Grand total**
```
subtotal $200 + fees $15 + shipping $0 + tax $0 = $215 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id  created_by
21  18        order         18          stripe_terminal  215.00  paid    reader_01                  pi_xxx                    ch_xxx            1
```

> Payment at pickup — 5 days after order creation. Serial already assigned when Emma arrived — payment was the final condition to advance to `processing`.

**No shipment row** — in-store pickup, no carrier involved.

**`inventory_serials`**
```
serial   status  location  note
SN-152   sold    NULL      with Emma Clark — back order pickup 2026-05-20
```

**`inventory_movements`**
```
id  serial   type     from         to           reference      notes
51  SN-152   receive  NULL         Warehouse A  PO-2026-011    back order stock arrived
52  SN-152   sale     Warehouse A  NULL         ORD-2026-018
```

---

### Back Order State Timeline

```
2026-05-15  order created       status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-19  serial assigned     status=back_ordered, payment_status=unpaid,  serial=SN-152
2026-05-20  Emma pays terminal  status=processing,   payment_status=paid,    serial=SN-152
2026-05-20  Emma takes unit     status=complete
```

---

### Financial Summary
```
charged:   $215.00
collected: $215.00  (Stripe Terminal — paid at pickup, 5 days after order)
refunded:  $0.00
net:       $215.00 ✓
```

> No shipping margin — in-store pickup, no label generated.

---

## Example 17 — ORD-019 — Phone Back Order (Prepaid Stripe Checkout, Carrier)

**Scenario:** CSR takes a phone order for an item out of stock. Customer prepays via Stripe Checkout link. Stock arrives 3 days later — serial assigned — order advances to processing — ships — delivered.

**Customer 17: Ryan Foster**
- `customers.id = 17`, `customer_addresses.id = 18` (home address — 321 Birch Ave, Tucson AZ 85701)
- Source: `phone`
- Payment method: `stripe_checkout` (async — admin sends link, customer pays on Stripe hosted page)

---

### Data: `orders` row
```
id:                   19
number:               ORD-2026-0019
customer_id:          17
source:               phone
status:               back_ordered          ← set at creation (serial=NULL)
payment_status:       unpaid                ← set at creation
created_by:           3                     ← CSR who took the call
subtotal:             220.00                ← unit_price + tax_amount
fees:                 0.00
shipping:             20.00
grand_total:          240.00
currency:             USD
billing_*:            NULL                  ← stripe_checkout — Stripe hosted page handles billing
shipping_first_name:  Ryan
shipping_last_name:   Foster
shipping_email:       ryan.foster@email.com
shipping_phone:       520-555-0192
shipping_address_line1: 321 Birch Ave
shipping_city:        Tucson
shipping_state:       AZ
shipping_postal_code: 85701
shipping_country:     US
shipped_at:           NULL → 2026-05-20
shipped_by:           NULL → 5
delivered_at:         NULL → 2026-05-22
delivered_by:         NULL → 3
cancelled_at:         NULL
cancelled_by:         NULL
created_at:           2026-05-16 10:00:00
```

> Billing snapshot NULL — `stripe_checkout` Stripe hosted page captures billing. Phone source requires shipping snapshot (CSR collects address over the phone).

---

### Data: `order_lines` row
```
id:                   46
order_id:             19
sku:                  HDMI-4K-PRO
product_name:         4K HDMI Cable Pro 6ft
inventory_serial_id:  NULL              ← back-ordered — no serial yet
unit_price:           200.00
tax_rate:             0.0825
tax_amount:           16.50
line_total:           216.50
```

> `inventory_serial_id` NULL at creation = back-ordered line. Set to SN-153 when stock arrives from PO-2026-012.

---

### Data: `order_fees` — none
```
(no rows — order has no service fee)
```

---

### Data: `payments` row
```
id:                       22
order_id:                 19
payable_type:             order
payable_id:               19
method:                   stripe_checkout
amount:                   240.00
status:                   pending           ← created pending; webhook sets → paid
created_by:               3
currency:                 USD
stripe_checkout_session_id: cs_live_abc789xyz
stripe_payment_intent_id: NULL              ← stripe_checkout: no intent/charge IDs at creation
stripe_charge_id:         NULL
cash_received_at:         NULL
cheque_number:            NULL
paid_at:                  NULL → 2026-05-16 11:30:00   ← set by webhook on checkout.session.completed
paid_by:                  NULL              ← webhook-confirmed, no admin action
created_at:               2026-05-16 10:05:00
```

> `stripe_checkout` is async: payment row created with `status=pending` immediately after order. Webhook fires `checkout.session.completed` → `payments.status → paid` → `orders.payment_status → paid`. Order stays `back_ordered` (serial still NULL).

---

### Data: `inventory_movements` rows (2 total)
```
-- Movement 1: serial assigned from arriving PO stock
id:           53
serial_id:    153  (SN-153)
type:         back_order_fill      ← serial assigned to fulfill back order
order_line_id: 46
from_location: PO-2026-012         ← arriving purchase order
to_location:   ORDER-019           ← assigned to order
created_by:   3
created_at:   2026-05-19 09:15:00

-- Movement 2: sale movement when order ships
id:           54
serial_id:    153
type:         sale
order_line_id: 46
from_location: Warehouse A
to_location:   NULL                ← leaves inventory
created_by:   5                    ← warehouse staff who shipped
created_at:   2026-05-20 08:30:00
```

---

### Data: `shipments` row
```
id:                   41
shippable_type:       order
shippable_id:         19
customer_address_id:  18            ← Ryan's home address
direction:            outbound
carrier:              FedEx
tracking:             7489234721034
label_cost:           9.00
status:               pending → in_transit → delivered
created_by:           5
shipped_at:           2026-05-20 08:30:00
returned_at:          NULL
delivered_at:         2026-05-22 14:00:00
delivered_by:         3
created_at:           2026-05-20 08:00:00
```

---

### Back Order State Timeline
```
2026-05-16 10:00  order created         status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-16 10:05  checkout link sent    status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-16 11:30  webhook — Ryan paid   status=back_ordered, payment_status=paid,    serial=NULL
2026-05-19 09:15  serial assigned       status=processing,   payment_status=paid,    serial=SN-153
2026-05-20 08:30  admin ships           status=shipped
2026-05-22 14:00  delivered             delivered_at set
```

> `back_ordered` trigger: serial=NULL at creation (not payment). Payment advances `payment_status` independently. `processing` only when BOTH `payment_status=paid` AND all serials set.

---

### Financial Summary
```
charged:   $240.00
collected: $240.00  (Stripe Checkout — prepaid 90 min after order)
refunded:  $0.00
net:       $240.00 ✓
```

### Shipping Margin
```
revenue:   $20.00   (orders.shipping)
cost:      -$9.00   (shipments.label_cost)
margin:    +$11.00
```

---

## Example 18 — ORD-020 — Phone Back Order (Pay When Stock Arrives, Stripe Terminal)

**Scenario:** CSR takes a phone order for an out-of-stock item. No payment at creation. Stock arrives 4 days later — admin calls customer, collects payment via Stripe Terminal — serial assigned — both conditions met — ships — delivered.

**Customer 18: Marcus Webb**
- `customers.id = 18`, `customer_addresses.id = 19` (home — 88 Maple St, Denver CO 80201)
- Source: `phone`
- Payment method: `stripe_terminal` (admin reads card over terminal after stock arrives)

---

### Data: `orders` row
```
id:                   20
number:               ORD-2026-0020
customer_id:          18
source:               phone
status:               back_ordered          ← set at creation (serial=NULL)
payment_status:       unpaid                ← no payment at creation
created_by:           3
subtotal:             162.38                ← sum of order_lines.line_total
fees:                 10.00
shipping:             15.00
grand_total:          187.38
currency:             USD
billing_*:            NULL                  ← stripe_terminal — card-present, no billing entry
shipping_first_name:  Marcus
shipping_last_name:   Webb
shipping_email:       marcus.webb@email.com
shipping_phone:       720-555-0147
shipping_address_line1: 88 Maple St
shipping_city:        Denver
shipping_state:       CO
shipping_postal_code: 80201
shipping_country:     US
shipped_at:           NULL → 2026-05-21
shipped_by:           NULL → 5
delivered_at:         NULL → 2026-05-23
delivered_by:         NULL → 3
cancelled_at:         NULL
cancelled_by:         NULL
created_at:           2026-05-17 14:00:00
```

> Billing snapshot NULL — `stripe_terminal` card-present, no manual billing entry. Shipping snapshot filled — CSR collects address over phone at order creation.

---

### Data: `order_lines` row
```
id:                   47
order_id:             20
sku:                  USB-C-HUB-7
product_name:         7-Port USB-C Hub
inventory_serial_id:  NULL              ← back-ordered — no serial yet
unit_price:           150.00
tax_rate:             0.0825
tax_amount:           12.38             ← 150.00 × 0.0825 = 12.375 → rounded
line_total:           162.38
```

> `inventory_serial_id` NULL at creation. Set to SN-154 when stock arrives from PO-2026-013.

---

### Data: `order_fees` row
```
id:        26
order_id:  20
name:      Service Fee
amount:    10.00
```

---

### Data: `payments` row — inserted when stock arrives and admin calls customer
```
id:                         23
order_id:                   20
payable_type:               order
payable_id:                 20
method:                     stripe_terminal
amount:                     187.38
status:                     paid              ← instant-paid (card-present)
created_by:                 3                 ← CSR who called Marcus back
currency:                   USD
stripe_terminal_reader_id:  tmr_abc123
stripe_payment_intent_id:   pi_xyz789
stripe_charge_id:           ch_xyz789
stripe_checkout_session_id: NULL
cash_received_at:           NULL
cheque_number:              NULL
paid_at:                    NULL              ← instant-paid: use created_at
paid_by:                    NULL              ← instant-paid: no separate confirmation step
created_at:                 2026-05-21 09:00:00
```

> No payment row at order creation — `payment_status=unpaid` with no payments record until stock arrives. `stripe_terminal` = instant paid; `paid_at` / `paid_by` stay NULL — `created_at` is the payment timestamp.

---

### Data: `inventory_movements` rows (2 total)
```
-- Movement 1: serial assigned from arriving PO stock
id:            55
serial_id:     154  (SN-154)
type:          back_order_fill
order_line_id: 47
from_location: PO-2026-013
to_location:   ORDER-020
created_by:    3
created_at:    2026-05-21 08:45:00

-- Movement 2: sale movement when order ships
id:            56
serial_id:     154
type:          sale
order_line_id: 47
from_location: Warehouse A
to_location:   NULL
created_by:    5
created_at:    2026-05-21 11:00:00
```

---

### Data: `shipments` row
```
id:                   42
shippable_type:       order
shippable_id:         20
customer_address_id:  19
direction:            outbound
carrier:              UPS
tracking:             1Z999AA10123456784
label_cost:           8.50
status:               pending → in_transit → delivered
created_by:           5
shipped_at:           2026-05-21 11:00:00
returned_at:          NULL
delivered_at:         2026-05-23 15:30:00
delivered_by:         3
created_at:           2026-05-21 10:30:00
```

---

### Back Order State Timeline
```
2026-05-17 14:00  order created       status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-21 08:45  serial assigned     status=back_ordered, payment_status=unpaid,  serial=SN-154
                                      ← serial set but unpaid — stays back_ordered
2026-05-21 09:00  admin calls Marcus  payment inserted → payment_status=paid
                  stripe_terminal     status=processing    ← both conditions met
2026-05-21 11:00  admin ships         status=shipped
2026-05-23 15:30  delivered           delivered_at set
```

> Key distinction from Ex 17: serial assigned BEFORE payment (stock arrives, then admin calls customer). `status` stays `back_ordered` after serial assignment because `payment_status=unpaid`. Advances to `processing` only after payment collected — both conditions required simultaneously.

---

### Financial Summary
```
charged:   $187.38
collected: $187.38  (Stripe Terminal — paid when stock arrived, 4 days after order)
refunded:  $0.00
net:       $187.38 ✓
```

### Shipping Margin
```
revenue:   $15.00
cost:      -$8.50
margin:    +$6.50
```

---

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
