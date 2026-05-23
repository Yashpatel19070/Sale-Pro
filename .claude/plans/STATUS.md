# Project Status

> Updated: 2026-05-22 (core-returns 01-schema.md plan file created)
> Rule: Claude must update this file at the end of every task.

| Module | Status | % | Last Touched | Notes |
|---|---|---|---|---|
| user | done | 100% | — | CRUD, roles, permissions, tests |
| permissions | done | 100% | — | Spatie, seeders, policy |
| department | done | 100% | — | CRUD, tests |
| customer | done | 100% | 2026-05-21 | CRUD, status, force-verify, password reset, tests |
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
| order | not started | 0% | — | Schema plan only; unblocks payment/shipment/refund |
| payment | not started | 0% | — | Depends on order |
| shipment | not started | 0% | — | Depends on order |
| refund | not started | 0% | — | Depends on order |
| replacement | not started | 0% | — | Depends on order |
| complaint | not started | 0% | — | Schema plan only |
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
