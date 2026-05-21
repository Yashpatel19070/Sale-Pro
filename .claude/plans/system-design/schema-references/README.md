# Schema References — Index

Dummy data illustrating the target DB schema. Each example is a complete scenario (orders, complaints, payments, shipments, replacements, refunds) showing how tables relate and how rows look in real flows.

**Agents:** load `globals.md` + the example(s) closest to your task before writing migrations or queries.

---

## Files

### Shared
| File | Contents |
|------|----------|
| [globals.md](./globals.md) | Agent rules, payment rule, staff users, status enums, inventory movements, oversell prevention, warehouse write-off, payments, shipments, refunds, notes, replacements |

### Examples
| File | Order # | Scenario |
|------|---------|----------|
| [example-01.md](./example-01.md) | ORD-001 | Clean Stripe card order |
| [example-02.md](./example-02.md) | ORD-002 | Clean cash order |
| [key-differences-01-02-03.md](./key-differences-01-02-03.md) | — | Comparison table across Examples 1, 2, 3 |
| [example-03.md](./example-03.md) | ORD-004 | Flow A — no fault, unit returned |
| [example-04.md](./example-04.md) | ORD-005 | Multi-line, concurrent complaints |
| [example-05.md](./example-05.md) | ORD-006 | Flow B — no fault, charged replacement |
| [example-06.md](./example-06.md) | ORD-007 | Flow A — damaged by customer, charged replacement |
| [example-07.md](./example-07.md) | ORD-009 | Post-delivery return, full refund |
| [example-08.md](./example-08.md) | ORD-010 | Flow B — unit never returned, open case |
| [example-09.md](./example-09.md) | ORD-011 | Phone order, in-store pickup, in-person complaint |
| [example-10.md](./example-10.md) | ORD-012 | Phone order, in-store pickup, chained complaint, full refund |
| [example-11.md](./example-11.md) | ORD-013 | Walk-in + Stripe checkout |
| [example-12.md](./example-12.md) | ORD-014 | Phone order + cheque |
| [example-13.md](./example-13.md) | ORD-015 | Withdrawn complaint |
| [example-14.md](./example-14.md) | ORD-016 | Return to sender, re-shipped |
