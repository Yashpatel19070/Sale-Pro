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
