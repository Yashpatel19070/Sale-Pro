# Refund Module — Schema

## Purpose

Records refunds issued against orders. Created manually by admin — never auto-created by the system. Two types: direct order returns (no complaint) and complaint escalations (examination resolved in customer's favour or two consecutive faults).

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `refunds` | Manual refund records — amount, method, type, status |

---

## Table: `refunds`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| number | string(20) | No | — | Unique — e.g. `REF-2026-0001`. Generated from `sequences` table |
| order_id | foreignId | No | — | FK → orders.id — always the originating order |
| type | string(20) | No | — | `order` / `complaint` — determines what `payable_id` references |
| payable_id | unsignedBigInteger | No | — | FK to orders.id (type=order) or complaints.id (type=complaint) |
| amount | decimal(12,2) unsigned | No | — | Product refund amount — may be less than grand_total if restocking fee applied |
| ship_refund | decimal(10,2) unsigned | No | 0.00 | Shipping refund — 0.00 if free shipping or shipping not refunded |
| method | string(10) | No | — | `stripe` / `cash` / `cheque` — simplified enum, see method mapping below |
| reason | text | Yes | null | Admin notes on refund reason |
| status | string(20) | No | `'pending'` | `pending` / `processed` |
| currency | char(3) | No | `'USD'` | ISO 4217 |
| created_by | foreignId | No | — | FK → users.id — admin who initiated the refund |
| processed_by | foreignId | Yes | null | FK → users.id — who processed the refund (may equal created_by for small teams) |
| processed_at | timestamp | Yes | null | When refund completed |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `number` — unique index
- `order_id` — foreign key index
- `(type, payable_id)` — polymorphic lookup index

---

## Status Enums

### `refunds.status`
| Value | Meaning |
|-------|---------|
| `pending` | Refund initiated, not yet processed (Stripe async refund) |
| `processed` | Money returned to customer |

### `refunds.type` (polymorphic)
| `type` | `payable_id` points to | When used |
|--------|----------------------|-----------|
| `order` | `orders.id` | Direct return — customer returns order, no complaint involved |
| `complaint` | `complaints.id` | Complaint escalated to refund after examination decision |

---

## Amount rules

```
total_refund = amount + ship_refund
```

| Scenario | `amount` | `ship_refund` |
|----------|---------|--------------|
| Full refund, good condition | `grand_total - shipping` (full product value) | shipping amount if charged, else 0.00 |
| Partial refund (restocking fee) | `grand_total - restocking_fee - shipping` | admin decision |
| Free shipping | — | 0.00 (nothing to refund) |

> `amount` is 100% admin decision — no auto-calculation enforced in code.

---

## Effect on order

On `refunds` INSERT:
- `orders.status → refunded`
- `orders.cancelled_at = now()` (reused as terminal timestamp for refund event)
- `orders.cancelled_by = auth user`
- `orders.payment_status` stays `paid` — refund tracked separately in `refunds` table

---

## Refund method mapping

`refunds.method` is a simplified 3-value enum — all Stripe variants collapse to `stripe`.

| Original `payments.method` | `refunds.method` | How |
|---------------------------|-----------------|-----|
| `stripe_card` | `stripe` | Stripe API refund to original card |
| `stripe_terminal` | `stripe` | Stripe API refund to original card |
| `stripe_checkout` | `stripe` | Stripe API refund to original card |
| `cash` | `cash` | Physical cash returned at counter |
| `cheque` | `cheque` | Admin issues cheque or cash equivalent |

---

## Refund Number Format

- Format: `REF-{YEAR}-{SEQUENCE}` e.g. `REF-2026-0001`
- Generated in `RefundService::generateNumber()`

---

## Migration Order

```
1. orders       (order_id FK)
2. complaints   (payable_id FK for type=complaint)
3. users        (processed_by FK — already exists)
4. refunds      (depends on: orders, complaints)
```

> `payable_id` is polymorphic — no real DB FK constraint. Only `order_id`, `created_by`, and `processed_by` are real FKs.

---

## Relationships Summary

```
Order hasMany Refunds
Complaint hasMany Refunds (polymorphic)
Refund belongsTo Order
Refund morphsTo Payable (order or complaint)
Refund belongsTo User (created_by)
Refund belongsTo User (processed_by)
```
