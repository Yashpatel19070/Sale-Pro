# Schema References — Index

> Read [global.md](global.md) before any example.
> Order events reference: [examples/order-events.md](examples/order-events.md)

## Order Examples

| # | File | Order | Scenario |
|---|------|-------|---------|
| Ex-1  | [ex-01-clean-stripe-order.md](examples/ex-01-clean-stripe-order.md) | ORD-001 | Online order, Stripe card, delivered, no issues |
| Ex-2  | [ex-02-clean-cash-order.md](examples/ex-02-clean-cash-order.md) | ORD-002 | Walk-in, cash, 2 items, FedEx delivery, no issues |
| Ex-3  | [ex-03-no-fault-returned.md](examples/ex-03-no-fault-returned.md) | ORD-004 | Walk-in, cash, complaint Flow A — no fault, unit returned |
| Ex-4  | [ex-04-multi-line-complaints.md](examples/ex-04-multi-line-complaints.md) | ORD-005 | Multi-line, concurrent complaints, internal fault + damage |
| Ex-5  | [ex-05-flow-b-no-fault-charged.md](examples/ex-05-flow-b-no-fault-charged.md) | ORD-006 | Flow B replacement, no fault, charged replacement |
| Ex-6  | [ex-06-damaged-charged-rep.md](examples/ex-06-damaged-charged-rep.md) | ORD-007 | Flow A, damaged by customer, charged replacement |
| Ex-7  | [ex-07-post-delivery-refund.md](examples/ex-07-post-delivery-refund.md) | ORD-009 | Post-delivery return, full refund |
| Ex-8  | [ex-08-unit-never-returned.md](examples/ex-08-unit-never-returned.md) | ORD-010 | Flow B, unit never returned, open case |
| Ex-9  | [ex-09-phone-instore-pickup.md](examples/ex-09-phone-instore-pickup.md) | ORD-011 | Phone order, in-store pickup, in-person complaint |
| Ex-10 | [ex-10-chained-complaint-refund.md](examples/ex-10-chained-complaint-refund.md) | ORD-012 | Chained complaint, second fault → refund |
| Ex-11 | [ex-11-stripe-checkout.md](examples/ex-11-stripe-checkout.md) | ORD-013 | Walk-in, Stripe checkout QR |
| Ex-12 | [ex-12-cheque-payment.md](examples/ex-12-cheque-payment.md) | ORD-014 | Phone order, cheque payment |
| Ex-13 | [ex-13-withdrawn-complaint.md](examples/ex-13-withdrawn-complaint.md) | ORD-015 | Complaint withdrawn before shipping unit |
| Ex-14 | [ex-14-return-to-sender.md](examples/ex-14-return-to-sender.md) | ORD-016 | Return to sender, re-shipped to different address |
| Ex-15 | [ex-15-walkin-backorder-cash.md](examples/ex-15-walkin-backorder-cash.md) | ORD-017 | Walk-in back order, prepaid cash, carrier delivery |
| Ex-16 | [ex-16-walkin-backorder-pickup.md](examples/ex-16-walkin-backorder-pickup.md) | ORD-018 | Walk-in back order, pay at pickup, in-store |
| Ex-17 | [ex-17-phone-backorder-checkout.md](examples/ex-17-phone-backorder-checkout.md) | ORD-019 | Phone back order, prepaid Stripe checkout, carrier |
| Ex-18 | [ex-18-phone-backorder-terminal.md](examples/ex-18-phone-backorder-terminal.md) | ORD-020 | Phone back order, pay when stock arrives, Stripe terminal |

## Core Return Examples

| # | File | Scenario |
|---|------|---------|
| CR-1  | [cr-01-counter-accept-rebuild.md](examples/cr-01-counter-accept-rebuild.md) | Counter, accepted, 30-day expires → rebuild |
| CR-2  | [cr-02-counter-accept-reclaim.md](examples/cr-02-counter-accept-reclaim.md) | Counter, accepted, customer reclaims within 30 days |
| CR-3  | [cr-03-counter-reject-takeback.md](examples/cr-03-counter-reject-takeback.md) | Counter, rejected, customer takes core back |
| CR-4  | [cr-04-counter-reject-scrapped.md](examples/cr-04-counter-reject-scrapped.md) | Counter, rejected, scrapped |
| CR-5  | [cr-05-counter-reject-disposed.md](examples/cr-05-counter-reject-disposed.md) | Counter, rejected, disposed (hazmat) |
| CR-6  | [cr-06-mail-accept-rebuild.md](examples/cr-06-mail-accept-rebuild.md) | Mail, accepted, 30-day expires → rebuild |
| CR-7  | [cr-07-mail-accept-reclaim.md](examples/cr-07-mail-accept-reclaim.md) | Mail, accepted, customer reclaims → we ship back |
| CR-8  | [cr-08-mail-reject-shipback.md](examples/cr-08-mail-reject-shipback.md) | Mail, rejected, we ship core back |
| CR-9  | [cr-09-mail-reject-disposed.md](examples/cr-09-mail-reject-disposed.md) | Mail, rejected, disposed |
| CR-10 | [cr-10-fraud-blocked.md](examples/cr-10-fraud-blocked.md) | Fraud blocked — serial matches our inventory |
| CR-11 | [cr-11-full-chain.md](examples/cr-11-full-chain.md) | Full chain — fraud → mail → accept → reclaim → second return → rebuild |
