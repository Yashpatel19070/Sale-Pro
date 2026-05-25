# TDD-Order — Overview

> Single source of truth for the order module.
> Every other file in this directory derives from facts established here.
> **No code blocks in any plan file. Rules, constraints, and test contracts only.**

---

## Scope

This plan covers the full lifecycle of one order:

```
create → pay → ship → deliver
                    ↘
                  cancel → delete
                  (pending or processing only)
```

Out of scope (separate modules): Stripe payments, refunds, replacements, complaints, backorders.

---

## Business Rules

### Status transitions — authoritative

| From | To | Trigger | Who |
|---|---|---|---|
| — | `pending` | order created | admin |
| `pending` | `processing` | cash payment recorded | admin |
| `processing` | `shipped` | admin ships | admin |
| `shipped` | (stays `shipped`) | admin marks delivered | admin |
| `pending` | `cancelled` | admin cancels | admin |
| `processing` | `cancelled` | admin cancels | admin |

**`delivered_at` is set on the order; `status` stays `shipped`. There is no `complete` transition in this module.**

`OrderStatus::Complete`, `Refunded`, `BackOrdered`, `Rts` exist in the enum but are not triggered by this module.

### Financial rules

- `orders.subtotal` = sum of all `order_lines.line_total`
- `order_lines.line_total` = `unit_price + tax_amount` (tax is baked into line_total and into subtotal)
- `orders.grand_total` = `subtotal + fees + shipping` (do NOT add tax again — it is already in subtotal)
- `orders.fees` = sum of all `order_fees.amount`
- Displaying totals: label subtotal as "Subtotal (incl. tax)" — there is no separate order-level tax column

### Billing snapshot rules (from migration comments)

- **Cash payment** → billing snapshot is NULL (no card, no billing address needed)
- **Stripe card / terminal / checkout / cheque** → billing snapshot required
- This module handles cash only. Billing default on create form = "None" (not "Same as Shipping")

### Shipping snapshot rules

- NULL allowed for in-store pickup orders
- Required when a carrier shipment exists (FedEx, UPS, etc.)
- Snapshot is written at order creation from `CustomerAddress` — immutable after create

### Address resolution (create and update)

Three cases, in order of precedence:
1. `address_id` provided → use existing `CustomerAddress` record, no new row
2. Inline fields (`line1`, `city`, etc.) provided → create new `CustomerAddress`, use it
3. Neither → snapshot columns all NULL (no shipment address)

### Delete rules

- Only `cancelled` orders can be deleted
- Delete order_fees first, then order_lines, then order — explicit, not cascade
- Payments are preserved (not deleted) — accounting record

---

## Examples (canonical reference)

| Example | Scenario | Key constraint verified |
|---|---|---|
| ex-01 | Online Stripe card | billing snapshot filled, shipping snapshot filled |
| ex-02 | Walk-in cash | billing snapshot NULL, shipping snapshot filled |

Both examples confirm: `orders.status` stays `shipped` after delivery.

---

## Module files

| File | Purpose |
|---|---|
| `01-schema.md` | Authoritative column names, types, casts, enums |
| `02-permissions.md` | Full permission matrix |
| `03-service-spec.md` | Service method rules — no code |
| `04-requests-spec.md` | FormRequest rules — no code |
| `05-controller-spec.md` | Controller action rules — no code |
| `06-view-spec.md` | View column map + Alpine rules |
| `07-tests-spec.md` | All test names + assertions (TDD contract) |
