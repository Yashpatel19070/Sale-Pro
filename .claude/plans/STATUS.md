# Project Status

> Updated: 2026-05-25 (Order module GREEN: 162/162 unit+feature tests passing; 461 total passing, 0 failing)
> Rule: Claude must update this file at the end of every task.

| Module | Status | % | Last Touched | Notes |
|---|---|---|---|---|
| user | done | 100% | — | CRUD, roles, permissions, tests |
| permissions | done | 100% | — | Spatie, seeders, policy |
| department | done | 100% | — | CRUD, tests |
| customer | done | 100% | 2026-05-21 | CRUD, status, force-verify, password reset, tests |
| customer-address | done | 100% | 2026-05-25 | Admin + portal, auto-default, IDOR protection, tests; fixed wrong is_default assertion |
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
| order | done | 100% | 2026-05-25 | TDD GREEN: 162/162 order tests pass. All bugs fixed. Views audited against spec: billingType default fixed (create), product_name snapshot fallback fixed (edit). |
| payment | not started | 0% | — | Depends on order |
| shipment | not started | 0% | — | Depends on order |
| refund | not started | 0% | — | Depends on order |
| replacement | not started | 0% | — | Depends on order |
| complaint | not started | 0% | — | Schema plan only |
| note | not started | 0% | — | Schema plan only |
| core-returns | in progress | 20% | 2026-05-22 | 01-schema.md plan created; 11 examples in system-design/examples/; migrations/models/services not started |
| admin-search | in progress | 80% | 2026-05-24 | AJAX search endpoints live (product-listings, inventory-locations, inventory-serials); order create fully AJAX (no preload); build passing; 37/37 order tests pass |
| avatax | in progress | 95% | 2026-05-24 | Inline tax preview done: POST /orders/tax-preview endpoint + Alpine.js @change wiring; tax updates on serial select + price change; commit/void for payment phase not yet implemented |

## % Guide

| % | Milestone |
|---|---|
| 20% | Schema + migration |
| 40% | Model + service |
| 60% | Controller + routes |
| 80% | Views |
| 90% | Tests written |
| 100% | All tests passing |
