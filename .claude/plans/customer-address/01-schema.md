# Customer Address Module — Schema

## Purpose

Stores physical addresses for customers. Sub-resource of the customer module.
Used by orders (billing + shipping snapshots), shipments (actual per-shipment address FK).

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `customer_addresses` | Physical address records per customer |

---

## Table: `customer_addresses`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| customer_id | foreignId | No | — | FK → customers.id, cascade delete |
| label | string(50) | No | — | e.g. `Home`, `Work`, `Billing` |
| first_name | string(100) | No | — | Recipient first name |
| last_name | string(100) | No | — | Recipient last name |
| email | string(255) | Yes | null | Optional contact email for shipment |
| phone | string(30) | Yes | null | Optional contact phone |
| address_line1 | string(255) | No | — | Street address |
| address_line2 | string(255) | Yes | null | Apt, suite, unit |
| city | string(100) | No | — | |
| state | string(10) | No | — | e.g. `TX`, `AZ` |
| postal_code | string(20) | No | — | |
| country | char(2) | No | `'US'` | ISO 3166-1 alpha-2 |
| is_default | boolean | No | `false` | One default per customer |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |
| deleted_at | timestamp | Yes | null | Soft delete |

### Indexes
- `customer_id` — foreign key index
- `(customer_id, is_default)` — composite: fast lookup of default address per customer

### Notes
- Soft deletes (`deleted_at`) — hard delete blocked: `shipments.customer_address_id` is a real FK. Admin "removes" an address but record stays for historical shipment references
- Only one `is_default = true` per customer — enforced in service layer (not DB constraint)
- `email` + `phone` on address may differ from customer's primary email/phone (e.g. work address with work phone)
- At order creation: billing/shipping snapshots are copied FROM this table into `orders` columns — snapshots are then immutable
- At re-ship (RTS): new address row added, referenced via `shipments.customer_address_id` — order snapshot unchanged

---

## Migration Order

```
1. customer_addresses   (depends on: customers)
```

---

## Relationships Summary

```
Customer hasMany CustomerAddresses
CustomerAddress belongsTo Customer
Shipment belongsTo CustomerAddress (nullable — null for inbound returns and in-store pickup)
```

---

## How Addresses Flow Into Orders

```
customer_addresses row
    ↓ copied at order creation (snapshot — immutable after this point)
orders.billing_* columns   (10 columns, NULL for non-card payments)
orders.shipping_* columns  (10 columns, NULL for in-store pickup)

customer_addresses row
    ↓ referenced directly (live FK — can change per shipment attempt)
shipments.customer_address_id
```

> Never read `orders.shipping_snapshot` for re-ship address.
> Always read `shipments.customer_address_id` → `customer_addresses` for the actual delivery address per attempt.
