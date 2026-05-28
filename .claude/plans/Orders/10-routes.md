# 10 — Routes

> **Layer 4 — Behavior.** Depends on `02-permissions.md`, `06-policy.md`, `11-controller.md`.

## Scope

Route declarations for the Orders module under `/admin/orders/*`. All routes live inside the admin middleware group in `routes/web.php`.

**Code-complete file** — route declarations ARE the spec (no logic to defer to implementation).

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| All routes under `/admin/orders/` prefix | Matches existing admin section structure (per `CLAUDE.md` routing architecture) |
| Helper routes (`calculate-tax`, `customer-addresses`, `listing-stock`) registered BEFORE `Route::resource` | Avoids `{order}` route-model-binding conflicts on similarly-shaped URLs |
| RESTful resource for CRUD (7 routes via `Route::resource`) | Laravel convention; index/create/store/show/edit/update/destroy |
| Action routes (`cash-payment`, `complete`) registered AFTER `Route::resource` | They use `{order}` parameter, can sit after without conflict |
| Route names use dot notation (`orders.index`, `orders.show`, `orders.cash-payment`) | Laravel convention |
| All routes inside `auth, load_perms, verified, active` middleware stack | Per `CLAUDE.md` admin stack |
| Route model binding via type-hints (`Order $order`, `Customer $customer`, `ProductListing $listing`) | Laravel auto-resolves from URL params |
| No customer-portal routes | Customer-side is out of scope (per `00-overview.md`) |

---

## File location

```
routes/web.php
```

Routes added inside the existing admin middleware group:

```php
Route::middleware(['auth', 'load_perms', 'verified', 'active'])
    ->prefix('admin')
    ->group(function () {
        // ... existing admin routes ...

        // ====== Orders module ======

        // Helper endpoints (must come BEFORE Route::resource to avoid {order} binding)
        Route::get('orders/customer-addresses/{customer}', [OrderController::class, 'customerAddresses'])
            ->name('orders.customer-addresses');

        Route::get('orders/listing-stock/{listing}', [OrderController::class, 'listingStock'])
            ->name('orders.listing-stock');

        Route::post('orders/calculate-tax', [OrderController::class, 'calculateTax'])
            ->name('orders.calculate-tax');

        Route::post('orders/customer-addresses', [OrderController::class, 'storeCustomerAddress'])
            ->name('orders.customer-addresses.store');

        // RESTful resource (7 routes)
        Route::resource('orders', OrderController::class);

        // Action routes (use {order} — after resource is fine)
        Route::post('orders/{order}/cash-payment', [OrderController::class, 'recordCashPayment'])
            ->name('orders.cash-payment');

        Route::post('orders/{order}/complete', [OrderController::class, 'complete'])
            ->name('orders.complete');
    });
```

---

## Full route table (12 routes)

| Verb | URI | Controller method | Route name | Notes |
|------|-----|-------------------|------------|-------|
| GET | `/admin/orders/customer-addresses/{customer}` | `customerAddresses` | `orders.customer-addresses` | Helper — JSON addresses for selected customer |
| GET | `/admin/orders/listing-stock/{listing}` | `listingStock` | `orders.listing-stock` | Helper — JSON stock count per location |
| POST | `/admin/orders/calculate-tax` | `calculateTax` | `orders.calculate-tax` | Helper — AvaTax computation per `08-avatax.md` |
| POST | `/admin/orders/customer-addresses` | `storeCustomerAddress` | `orders.customer-addresses.store` | Helper — JSON new-address creation (used by Create-order modal, per `12-views.md`) |
| GET | `/admin/orders` | `index` | `orders.index` | Index page |
| GET | `/admin/orders/create` | `create` | `orders.create` | Create form |
| POST | `/admin/orders` | `store` | `orders.store` | Submit new order |
| GET | `/admin/orders/{order}` | `show` | `orders.show` | Order detail page |
| GET | `/admin/orders/{order}/edit` | `edit` | `orders.edit` | Edit form (pending only) |
| PUT | `/admin/orders/{order}` | `update` | `orders.update` | Submit edited order |
| DELETE | `/admin/orders/{order}` | `destroy` | `orders.destroy` | Hard-delete pending order |
| POST | `/admin/orders/{order}/cash-payment` | `recordCashPayment` | `orders.cash-payment` | Record cash payment |
| POST | `/admin/orders/{order}/complete` | `complete` | `orders.complete` | Mark complete (handover) |

> **12 routes total** — 3 helpers + 7 resource + 2 actions.

---

## Route ordering rationale

```
1. orders/customer-addresses/{customer}  ← matches the literal "customer-addresses" string
2. orders/listing-stock/{listing}        ← matches the literal "listing-stock"
3. orders/calculate-tax                  ← matches the literal "calculate-tax"
─────────────────────────────────────────────
4. Route::resource('orders', ...)         ← creates orders/{order} bindings
─────────────────────────────────────────────
5. orders/{order}/cash-payment            ← {order} + literal "cash-payment"
6. orders/{order}/complete                ← {order} + literal "complete"
```

**Why helpers come first:** if `Route::resource` were declared first, Laravel would try to match `orders/calculate-tax` against `orders/{order}` and look up an Order with id `"calculate-tax"` — failing or 404'ing.

---

## Route model binding

Laravel auto-resolves these parameters via type-hint:

| URL param | Type | Resolves via |
|-----------|------|-------------|
| `{order}` | `Order` | `orders.id` |
| `{customer}` | `Customer` | `customers.id` |
| `{listing}` | `ProductListing` | `product_listings.id` |

> No explicit binding code needed in `RouteServiceProvider` — Laravel resolves by convention.

---

## Authorization at route level

Routes themselves don't enforce permissions. Authorization is handled inside each controller method via `$this->authorize('action', $resource)` (per `06-policy.md` + `11-controller.md`).

The middleware stack ensures only authenticated, verified, active users reach any of these routes. Permission checks happen per-action.

---

## ex-19 cross-reference

| ex-19 action | Route hit |
|--------------|-----------|
| Admin opens create form (line 20) | `GET /admin/orders/create` → `orders.create` |
| Admin picks customer → form fetches addresses | `GET /admin/orders/customer-addresses/{customer}` |
| Admin picks product → form fetches stock | `GET /admin/orders/listing-stock/{listing}` |
| Form computes tax for unit + fees | `POST /admin/orders/calculate-tax` |
| Admin submits new order (line 23-32) | `POST /admin/orders` → `orders.store` |
| Admin records cash payment (line 34-39) | `POST /admin/orders/{order}/cash-payment` |
| Admin marks complete on handover (line 46-51) | `POST /admin/orders/{order}/complete` |
| Admin opens order detail | `GET /admin/orders/{order}` → `orders.show` |

All ex-19 actions have a route.

---

## Dependencies

**Depends on:**
- `02-permissions.md` — permissions exist for each action
- `06-policy.md` — `OrderPolicy` enforced per action
- `11-controller.md` — `OrderController` action methods
- Existing middleware: `auth`, `load_perms`, `verified`, `active`

**Depended on by:**
- `11-controller.md` — controller method signatures must match URL params
- `12-views.md` — forms POST to these routes by name (`route('orders.store')`)
- `15-tests.md` — feature tests hit these routes via `route()` helper

---

## Validation gates

- [ ] All routes inside the admin middleware group
- [ ] Helper routes registered BEFORE `Route::resource`
- [ ] Resource produces 7 standard routes (index/create/store/show/edit/update/destroy)
- [ ] Action routes (`cash-payment`, `complete`) registered AFTER resource
- [ ] Every route has an explicit `->name(...)` matching the dot-notation convention
- [ ] Route model binding works for `Order`, `Customer`, `ProductListing` (test by hitting routes)
- [ ] No customer-portal routes added
- [ ] No `Route::apiResource` — full Blade UI uses `Route::resource`

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | Routes provide |
|--------|----------------|
| `02-permissions.md` 7 controller actions | Each has a route (`orders.viewAny → orders.index`, etc.) |
| `06-policy.md` policy methods | Each gates a route's controller method |
| `08-avatax.md` `POST /admin/orders/calculate-tax` | `orders.calculate-tax` route registered |
| `15-tests.md` feature tests use `route('orders.X', $order)` | Every route name referenced in tests is registered |
| `15-tests.md` `POST /admin/orders` for store | `orders.store` |
| `15-tests.md` `POST /admin/orders/{order}/cash-payment` | `orders.cash-payment` |
| `15-tests.md` `POST /admin/orders/{order}/complete` | `orders.complete` |

No gaps. Every test target route is defined.
