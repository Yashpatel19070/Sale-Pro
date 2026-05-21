# Payment Module — Schema

## Purpose

Records all payments against orders and replacement charge-backs. Polymorphic — covers both `order` payments (upfront) and `replacement` payments (after-examination charges). One payment row per transaction.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `payments` | All payment transactions — order payments and replacement charge-backs |

---

## Table: `payments`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id — **always set**, even for replacement payments |
| payable_type | string(30) | No | — | `order` or `replacement` |
| payable_id | unsignedBigInteger | No | — | FK to orders.id or replacements.id depending on payable_type |
| method | string(30) | No | — | Cast to `PaymentMethod` enum |
| amount | decimal(12,2) unsigned | No | — | Total charged |
| status | string(20) | No | — | Cast to `PaymentStatus` enum |
| created_by | foreignId | No | — | FK → users.id — admin who initiated the payment |
| currency | char(3) | No | `'USD'` | ISO 4217 |
| stripe_payment_intent_id | string(100) | Yes | null | stripe_card + stripe_terminal |
| stripe_charge_id | string(100) | Yes | null | stripe_card + stripe_terminal |
| stripe_terminal_reader_id | string(100) | Yes | null | stripe_terminal only |
| stripe_checkout_session_id | string(100) | Yes | null | stripe_checkout only |
| cheque_number | string(50) | Yes | null | cheque only |
| cheque_date | date | Yes | null | cheque only |
| cash_received_at | timestamp | Yes | null | cash only |
| paid_at | timestamp | Yes | null | When status went `pending` → `paid` (cheque cleared / webhook fired). NULL for instant-paid methods (stripe_card, stripe_terminal, cash) — use `created_at` for those. |
| paid_by | foreignId | Yes | null | FK → users.id — admin who confirmed payment. NULL for stripe_checkout (webhook). |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `order_id` — foreign key + reporting index (fetch all payments for an order)
- `(payable_type, payable_id)` — polymorphic lookup index
- `stripe_payment_intent_id` — webhook lookup (nullable, sparse index if supported)

---

## Status Enums

### `payments.method`
| Value | Source | Billing snapshot | Status flow |
|-------|--------|-----------------|-------------|
| `stripe_card` | online orders | Required (card-not-present) | instant `paid` |
| `stripe_terminal` | walk_in / phone | NULL (card-present) | instant `paid` |
| `cash` | walk_in / phone | NULL | instant `paid` (admin marks) |
| `stripe_checkout` | walk_in / phone | NULL (Stripe hosted) | `pending` → `paid` (webhook) |
| `cheque` | walk_in / phone | NULL | `pending` → `paid` (admin marks on clearance) |

### `payments.status`
| Value | Meaning |
|-------|---------|
| `pending` | Awaiting confirmation (stripe_checkout waiting for payment, cheque not yet cleared) |
| `paid` | Payment confirmed and collected |
| `expired` | stripe_checkout session timed out — no payment received |

> **Critical agent rule:** Never advance order to `processing` while `payments.status = pending`.
> `orders.payment_status = paid` is set only after `payments.status → paid`.
> `paid_at` + `paid_by` set when admin marks cheque cleared OR stripe_checkout webhook fires. For instant-paid methods (stripe_card, stripe_terminal, cash) both stay NULL — `created_at` is when payment was both created and paid.

---

## Payable type rules

| `payable_type` | `payable_id` points to | `order_id` value |
|----------------|----------------------|-----------------|
| `order` | `orders.id` | same as `payable_id` |
| `replacement` | `replacements.id` | parent order id (replacement's `order_id`) |

> Use `WHERE payments.order_id = ?` to fetch ALL payments for an order (direct + replacement charge-backs) without joining through `replacements`.

---

## Source → allowed method

| `orders.source` | Allowed `payments.method` |
|-----------------|--------------------------|
| `online` | `stripe_card` only |
| `walk_in` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |
| `phone` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |

---

## Method → columns filled

| method | Columns populated |
|--------|-------------------|
| `stripe_card` | `stripe_payment_intent_id`, `stripe_charge_id` |
| `stripe_terminal` | `stripe_terminal_reader_id`, `stripe_payment_intent_id`, `stripe_charge_id` |
| `cash` | `cash_received_at` |
| `stripe_checkout` | `stripe_checkout_session_id` |
| `cheque` | `cheque_number`, `cheque_date` |

---

## Webhook (stripe_checkout)

`stripe_checkout` requires a separate webhook endpoint — `PaymentWebhookController`.
Flow: Stripe fires `checkout.session.completed` → controller updates `payments.status → paid` → `orders.payment_status → paid` → `orders.status → processing`.

---

## Migration Order

```
1. orders         (must exist — order_id FK)
2. replacements   (must exist — payable_id FK for replacement payments)
3. payments       (depends on: orders, replacements)
```

> `payments` is created after `replacements` because `payable_id` can reference `replacements.id`.
> The `order_id` FK is a real DB constraint. `payable_id` is polymorphic (no DB-level FK).

---

## Relationships Summary

```
Order hasMany Payments
Replacement hasMany Payments (polymorphic)
Payment belongsTo Order (always — order_id)
Payment morphsTo Payable (order or replacement — payable_type/payable_id)
Payment belongsTo User (created_by)
Payment belongsTo User (paid_by — nullable)
```
