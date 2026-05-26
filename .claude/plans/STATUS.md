# Project Status

> Updated: 2026-05-26 (avatax Phase 2 implemented: calculateTax(), calculateTax endpoint, tax_exempt migration+model, form Alpine wiring, OrderService uses submitted tax_amount)
> Rule: Claude must update this file at the end of every task.

| Module | Status | % | Last Touched | Notes |
|---|---|---|---|---|
| user | done | 100% | — | CRUD, roles, permissions, tests |
| permissions | done | 100% | — | Spatie, seeders, policy |
| department | done | 100% | — | CRUD, tests |
| customer | done | 100% | 2026-05-26 | CRUD, status, force-verify, password reset, tests. tax_exempt: migration + model + fillable + cast + UpdateCustomerRequest rule + edit form checkbox + 3 Pest tests (28/28 passing). |
| customer-address | done | 100% | 2026-05-22 | Admin + portal, auto-default, IDOR protection, tests |
| portal-foundation | done | 100% | 2026-05-21 | Auth, middleware, layout, tests |
| portal-profile | done | 100% | 2026-05-22 | Profile, password change, tests |
| mail | in progress | 85% | 2026-05-21 | Phases 1-3 done; Phase 4 (AccountDeactivatedMail) deferred |
| supplier | done | 100% | — | CRUD, tests |
| product | done | 100% | — | CRUD, tests |
| product-category | done | 100% | — | CRUD, tests |
| product-listing | done | 100% | — | CRUD, SEO, slug, tests |
| purchase-order | done | 100% | — | PO + GRN + Invoice, tests |
| inventory | done | 100% | — | Location, movement, serial, tests |
| audit-log | done | 100% | — | Controller, views |
| order | done | 100% | 2026-05-26 | Serial assigned + sold + movement at full payment. Partial payment leaves inventory untouched. complete() only closes order. CSR shown on show page. Movement notes = "Order placed by {name}". Serial on show page links to serial detail. Edit form now mirrors create — customer, billing/shipping addresses, source, payment_method all editable on pending orders (UpdateOrderRequest + OrderService::update extended). 62/62 tests passing. |
| payment | not started | 0% | — | Depends on order |
| shipment | not started | 0% | — | Depends on order |
| refund | not started | 0% | — | Depends on order |
| replacement | not started | 0% | — | Depends on order |
| complaint | not started | 0% | — | Schema plan only |
| avatax | in progress | 95% | 2026-05-26 | Phase 1 done. Phase 2 done: AvaTaxService::calculateTax(), POST /admin/orders/calculate-tax, tax_exempt on Customer, Alpine fetchAllLineTax() debounce on create+edit forms, OrderService uses submitted tax_amount. Both forms: editable tax_amount input (admin can override AvaTax), edit form rewritten as table matching create (Product/SKU/Stock/UnitPrice/Tax$/Subtotal), onProductChange+loadStock on edit. 62/62 order tests passing. |
| note | not started | 0% | — | Schema plan only |
| core-returns | in progress | 20% | 2026-05-22 | 01-schema.md plan created; 11 examples in system-design/examples/; migrations/models/services not started |

## % Guide

| % | Milestone |
|---|---|
| 20% | Schema + migration |
| 40% | Model + service |
| 60% | Controller + routes |
| 80% | Views |
| 90% | Tests written |
| 100% | All tests passing |
