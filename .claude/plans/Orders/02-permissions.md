# 02 — Permissions

> **Layer 1 — Foundation.** No dependencies on other Orders plan files.

## Scope

Defines the Spatie permission slugs for the Orders module and the role × permission matrix for the 3 staff roles (`admin`, `manager`, `sales`).

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Dot-notation slugs (`orders.viewAny`, etc.) | Matches existing Customer module convention (e.g., `customers.viewAny`, `customers.update`) |
| 7 permissions total — one per controller action | Single permission per action keeps Policy gates simple |
| `sales` role gets everything EXCEPT `orders.delete` | Counter staff can sell + edit + complete, but not destroy records |
| `manager` and `admin` get all 7 | Full control |
| `orders.delete` = **permanent hard delete** | No soft-delete on orders. Only allowed on pending orders (no money, no inventory). AuditLogService records the deletion event before the row is wiped |
| No customer-portal permission | Customer side is out of scope for ex-19 |
| Seeded by `OrderPermissionSeeder` (defined in `13-seeders.md`) | Same pattern as `CustomerPermissionSeeder` |
| Granted via Spatie's `givePermissionTo()` in role seeders | Standard Spatie pattern |

---

## The 7 permissions

| Slug | Description | Gates which action |
|------|-------------|--------------------|
| `orders.viewAny` | Can see the orders index list | `OrderController::index` |
| `orders.view` | Can see a single order detail | `OrderController::show` |
| `orders.create` | Can create a new order | `OrderController::create`, `store` |
| `orders.update` | Can edit a pending order | `OrderController::edit`, `update` |
| `orders.delete` | Can **permanently delete** a pending order (hard delete, CASCADE wipes children) | `OrderController::destroy` |
| `orders.recordPayment` | Can record a cash payment on an order | `OrderController::recordCashPayment` |
| `orders.complete` | Can mark order complete (handover) | `OrderController::complete` |

---

## Role × permission matrix

| Permission | `admin` | `manager` | `sales` |
|------------|:-------:|:---------:|:-------:|
| `orders.viewAny`       | ✅ | ✅ | ✅ |
| `orders.view`          | ✅ | ✅ | ✅ |
| `orders.create`        | ✅ | ✅ | ✅ |
| `orders.update`        | ✅ | ✅ | ✅ |
| `orders.delete`        | ✅ | ✅ | ❌ |
| `orders.recordPayment` | ✅ | ✅ | ✅ |
| `orders.complete`      | ✅ | ✅ | ✅ |

**Rationale for `sales` not having `orders.delete`:** counter staff handle daily transactions but should not be able to permanently destroy records. Deletion = audit risk, restricted to manager+.

---

## Controller action → permission map (referenced by `06-policy.md`)

| Controller action | Permission checked | HTTP |
|-------------------|--------------------|------|
| `OrderController::index` | `orders.viewAny` | `GET /admin/orders` |
| `OrderController::show` | `orders.view` (also enforces order ownership via Policy) | `GET /admin/orders/{order}` |
| `OrderController::create` | `orders.create` | `GET /admin/orders/create` |
| `OrderController::store` | `orders.create` | `POST /admin/orders` |
| `OrderController::edit` | `orders.update` + Policy check (pending only) | `GET /admin/orders/{order}/edit` |
| `OrderController::update` | `orders.update` + Policy check (pending only) | `PUT /admin/orders/{order}` |
| `OrderController::destroy` | `orders.delete` + Policy check (pending only) — **permanent hard delete** | `DELETE /admin/orders/{order}` |
| `OrderController::recordCashPayment` | `orders.recordPayment` + Policy check (pending only) | `POST /admin/orders/{order}/cash-payment` |
| `OrderController::complete` | `orders.complete` + Policy check (processing only) | `POST /admin/orders/{order}/complete` |

---

## Dependencies

**Depends on:** nothing (Spatie Permission package already installed and configured).

**Depended on by:**
- `06-policy.md` — `OrderPolicy` calls `$user->can('orders.X')`
- `11-controller.md` — `$this->authorize('action', $order)` in each action
- `13-seeders.md` — `OrderPermissionSeeder` creates these 7 permissions + assigns to roles
- `15-tests.md` — tests use `givePermissionTo(['orders.viewAny', ...])` to act as different roles
- `16-audit-log.md` — `orders.delete` triggers `AuditLogService::log($order, 'deleted')` BEFORE the row is wiped

---

## Validation gates

- [ ] Every controller action in `11-controller.md` maps to exactly one permission
- [ ] Every role row has an explicit ✅ or ❌ for every permission
- [ ] No permission defined that isn't gated by an action
- [ ] No action defined without a permission gate
- [ ] Slugs use dot notation (`orders.<action>`)
- [ ] Seeder created in `13-seeders.md` covers all 7 permissions + role assignments
- [ ] `orders.delete` action documented as hard delete (no `SoftDeletes`)

---

## ex-19 cross-reference

ex-19 doesn't explicitly show roles or permissions, but every action maps to a permission:

| ex-19 action | Permission |
|--------------|-----------|
| "admin creates order at counter" (line 20) | `orders.create` |
| Order is viewable in admin panel | `orders.viewAny`, `orders.view` |
| "cash payment" recorded (line 34) | `orders.recordPayment` |
| Order edited before payment | `orders.update` |
| Order marked complete on handover (line 45) | `orders.complete` |
| Order deleted (admin cancels mistake) | `orders.delete` — permanent |

All `created_by = 1` references in ex-19 (lines 73, 124, 155-157) imply the staff member has the relevant permissions.
