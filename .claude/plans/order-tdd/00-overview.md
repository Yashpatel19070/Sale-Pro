# Order Module — TDD Plan Overview

**Scenario covered:** Ex-19 — Walk-in Cash, In-Store Pickup, No Address
`source=walk_in` + `method=cash` + `status → complete` + both snapshots NULL + no shipment row

---

## Files to Build

| Layer | File |
|-------|------|
| Migrations | `create_orders_table`, `create_order_lines_table`, `create_order_fees_table`, `create_payments_table`, `create_order_events_table` |
| Enums | `OrderStatus`, `OrderSource`, `PaymentMethod`, `PaymentStatus` |
| Models | `Order`, `OrderLine`, `OrderFee`, `Payment`, `OrderEvent` |
| Factories | `OrderFactory`, `OrderLineFactory`, `OrderFeeFactory`, `PaymentFactory`, `OrderEventFactory` |
| Policy | `OrderPolicy` |
| Service | `OrderService` |
| FormRequests | `StoreOrderRequest`, `UpdateOrderRequest`, `RecordCashPaymentRequest` |
| Controller | `OrderController` |
| Views | `orders/index`, `orders/create`, `orders/show`, `orders/edit` |
| Tests | `OrderServiceTest`, `OrderControllerTest` |

---

## Build Order (RED → GREEN → REFACTOR)

```
1. Migrations          → tables must exist before anything runs
2. Enums               → OrderStatus, OrderSource, PaymentMethod, PaymentStatus
3. Models + Factories  → needed by every test
4. Permissions + Policy → needed by feature tests
5. Routes              → needed by feature tests
6. Service unit tests  → write tests first (RED)
7. OrderService        → implement to make unit tests pass (GREEN)
8. FormRequests        → needed by controller
9. Controller feature tests → write tests first (RED)
10. OrderController    → implement to make feature tests pass (GREEN)
11. Views              → last — feature tests assert redirects not view content
```

---

## Plan Files

| File | Contents |
|------|----------|
| [01-foundation.md](01-foundation.md) | Migrations, Enums, Models, Factories, Permissions, Routes |
| [02-service.md](02-service.md) | Service methods + all unit tests |
| [03-requests.md](03-requests.md) | FormRequests — fields and validation rules |
| [04-controller.md](04-controller.md) | Controller actions + all feature tests |
| [05-views.md](05-views.md) | View structure and key elements per page |
| [06-flow-diagram.md](06-flow-diagram.md) | Request-to-data flow for all 3 actions (store, recordCashPayment, complete) |
