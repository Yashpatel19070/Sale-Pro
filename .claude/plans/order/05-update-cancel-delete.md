# Order — Update, Cancel, Delete

> **READ [`00-rules.md`](00-rules.md) FIRST.** Columns from [`01-schema.md`](01-schema.md). Service patterns from [`02-services.md`](02-services.md). Routes/views from [`03-controllers.md`](03-controllers.md). Tests from [`04-tests.md`](04-tests.md).

---

## ASK Triggers (specific to this file)

| # | Trigger | Question |
|---|---------|----------|
| 1 | About to allow update on an order with status other than `Pending` | "Spec restricts update to Pending — really allow `X` status? Confirm." |
| 2 | About to allow cancel on an order with status other than `Pending` / `Processing` | "Spec restricts cancel to Pending/Processing — really allow `X`? Confirm." |
| 3 | About to allow delete on an order with status other than `Cancelled` | "Spec restricts delete to Cancelled — really allow `X`? Confirm." |
| 4 | About to give `orders.delete` to a role other than `super_admin` | "Per matrix only super_admin has delete. Really grant to `X`?" |
| 5 | About to refund a cancelled paid order | "Refund logic belongs to the future Refund module — out of scope here. Confirm." |

---

## Section 1 — Why this file exists

The order module launched with: `viewAny, view, create, pay, ship, deliver`.
Complete modules (Customer, PurchaseOrder) also have: `update`, `delete`, plus domain actions like `cancel`.
The order schema already has `cancelled_at` + `cancelled_by` columns — cancel was designed, never wired.

This file closes all three gaps in one implementation pass.

---

## Section 2 — Permission Matrix (after this plan)

| Permission | super_admin | admin | manager | sales | Source |
|---|:---:|:---:|:---:|:---:|---|
| `orders.viewAny` | ✅ | ✅ | ✅ | ✅ | existing |
| `orders.view`    | ✅ | ✅ | ✅ | ✅ | existing |
| `orders.create`  | ✅ | ✅ | ✅ | ❌ | existing |
| **`orders.update`** | ✅ | ✅ | ✅ | ❌ | **this plan** |
| **`orders.cancel`** | ✅ | ✅ | ✅ | ❌ | **this plan** |
| **`orders.delete`** | ✅ | ❌ | ❌ | ❌ | **this plan** |
| `orders.pay`     | ✅ | ✅ | ✅ | ❌ | existing |
| `orders.ship`    | ✅ | ✅ | ✅ | ❌ | existing |
| `orders.deliver` | ✅ | ✅ | ✅ | ❌ | existing |

> Single source of truth — this table is mirrored in [`03-controllers.md`](03-controllers.md) §2.

---

## Section 3 — Routes (delta)

Add to existing `Route::prefix('orders')->name('orders.')->group()` in `routes/web.php`, **after** the `deliver` route.

| Verb | URL | Controller method | Route name |
|------|-----|-------------------|------------|
| GET    | `/{order}/edit`   | `edit`    | `orders.edit`    |
| PUT    | `/{order}`        | `update`  | `orders.update`  |
| DELETE | `/{order}`        | `destroy` | `orders.destroy` |
| POST   | `/{order}/cancel` | `cancel`  | `orders.cancel`  |

Do NOT switch to `Route::resource()` — see [`03-controllers.md`](03-controllers.md) §1.
No restore route — `orders` has no `deleted_at` column (hard delete only).

---

## Section 4 — Permission Seeder (delta)

**File:** `database/seeders/OrderPermissionSeeder.php`

> **Existing bug to fix:** current seeder does `$admin->givePermissionTo($permissions)` and gives admin the full array. Must change to explicit list excluding `orders.delete`.

### After-state (full spec is in [`03-controllers.md`](03-controllers.md) §3)

| Permission set | Roles granted | Excludes |
|----------------|---------------|----------|
| `$all` (9 keys) | super_admin | — |
| `$adminPerms` (8 keys — all except `orders.delete`) | admin, manager | `orders.delete` |
| `$viewOnly` (2 keys — viewAny, view) | sales | everything else |

Use `firstOrCreate` for each `Permission` row. Use `where()->first()?->` for `manager` and `sales` roles (they come from `RoleSeeder`).

**Reference:** [`skills/references/permissions-spatie.md`](../../skills/references/permissions-spatie.md).

---

## Section 5 — Policy (delta)

**File:** `app/Policies/OrderPolicy.php`

Add three methods — pure permission check, no status logic:

| Method | Signature | Check |
|--------|-----------|-------|
| `update`  | `(User $user, Order $order): bool` | `$user->can('orders.update')` |
| `cancel`  | `(User $user, Order $order): bool` | `$user->can('orders.cancel')` |
| `delete`  | `(User $user, Order $order): bool` | `$user->can('orders.delete')` |

> Status guards live in the service inside the transaction. The policy only checks permissions.

---

## Section 6 — FormRequest

Only `update` needs a FormRequest. `cancel` and `destroy` have no request body.

### `UpdateOrderRequest`

**File:** `app/Http/Requests/Order/UpdateOrderRequest.php`
**authorize():** `$this->user()->can('update', $this->route('order'))` — passes bound `Order` model so policy `update()` fires.

#### Field map (request key → DB column)

Same as [`02-services.md`](02-services.md) §C1 **except**:

| Key | Status | Reason |
|-----|--------|--------|
| `customer_id` | **removed** | customer is not editable after order creation |
| `lines.*` | **removed** | line items are not editable after order creation (would invalidate AvaTax, oversell guards, etc.) |

All other keys (source, shipping_amount, fees, billing.*, shipping.*, billing_same_as_shipping) keep the same rules as `CreateOrderRequest` — with two adjustments:

| Key | Rule change |
|-----|-------------|
| `fees` | `nullable\|array` (was `nullable\|array` already — explicit: zero fees allowed on edit) |
| `fees.*.name` | `required_with:fees` (was `required_with:fees` already) |

#### prepareForValidation()
Not needed (no `customer_id` to cast).

---

## Section 7 — Service (delta)

**File:** `app/Services/OrderService.php`
**Reference:** [`skills/references/service.md`](../../skills/references/service.md#throw-domainexception-for-expected-business-failures) · [`skills/references/service.md`](../../skills/references/service.md#toctou--business-rule-guards-must-be-inside-the-transaction).

---

### 7A — Snapshot helpers — make nullable (prerequisite)

Already specified in [`02-services.md`](02-services.md) §D10/D11 — `shippingSnapshot(?CustomerAddress)` and `billingSnapshot(?CustomerAddress)` accept null and use `?->` accessors. Call sites in `create()` spread the result unconditionally (no outer ternary).

This is a **prerequisite** for `update()` because update() must always write all 20 snapshot columns — including clearing them when the user changes address to none.

---

### 7B — `update(Order $order, array $data, User $authActor): Order`

**Wrapping:** `DB::transaction(...)`.

| # | Step |
|---|------|
| 1 | **Guard (TOCTOU inside transaction):** if `$order->status !== OrderStatus::Pending` → `\DomainException("Only pending orders can be edited.")` |
| 2 | Resolve shipping address via `resolveAddress($order->customer_id, $data['shipping'] ?? [])` |
| 3 | Resolve billing address: if `billing_same_as_shipping` true, reuse shipping; else `resolveAddress($order->customer_id, $data['billing'] ?? [])` |
| 4 | Delete existing fees: `$order->orderFees()->delete()` |
| 5 | Recreate fees: for each `$data['fees'] ?? []` → insert `order_fees` row |
| 6 | Compute new fee total: `array_sum(array_column($data['fees'] ?? [], 'amount'))` |
| 7 | Compute shipping: `(float) $data['shipping_amount']` |
| 8 | Subtotal stays the same: `$subtotal = $order->subtotal` (lines unchanged) |
| 9 | Update order: `source`, `fees = $feeTotal`, `shipping = $shipping`, `grand_total = $subtotal + $feeTotal + $shipping`, all 10 `shipping_*` cols (via snapshot helper), all 10 `billing_*` cols (via snapshot helper) |
| 10 | Return `$order->fresh()` |

> Fee wipe + recreate is safe — only pending orders can update, and pending orders are not yet invoiced.

---

### 7C — `cancel(Order $order, User $authActor): Order`

**Wrapping:** None — single-table write.

| # | Step |
|---|------|
| 1 | **Guard:** if `! in_array($order->status, [OrderStatus::Pending, OrderStatus::Processing])` → `\DomainException("Only pending or processing orders can be cancelled.")` |
| 2 | Update order: `status = OrderStatus::Cancelled`, `cancelled_at = now()`, `cancelled_by = $authActor->id` |
| 3 | Return `$order->fresh()` |

> **Inventory note:** serials are only marked `Sold` at ship time. Cancelling a pending/processing order requires **no** inventory changes — serials remain in stock automatically.
> **Payment note:** if a paid processing order is cancelled, the `payments` row stays. Refunds belong to the future Refund module.

---

### 7D — `delete(Order $order): void`

**Wrapping:** `DB::transaction(...)`.

| # | Step |
|---|------|
| 1 | **Guard:** if `$order->status !== OrderStatus::Cancelled` → `\DomainException("Only cancelled orders can be deleted.")` |
| 2 | `$order->orderFees()->delete()` (explicit — not relying on DB cascade) |
| 3 | `$order->lines()->delete()` (explicit) |
| 4 | `$order->delete()` |

> **Does NOT delete payments** — cancelled-order payment records are preserved for accounting. Reconciliation belongs to the future Refund module.

---

## Section 8 — Controller (delta)

**File:** `app/Http/Controllers/OrderController.php`
**Reference:** [`skills/references/controller.md`](../../skills/references/controller.md#full-crud-pattern--blade) · [`skills/references/controller.md`](../../skills/references/controller.md#handling-service-exceptions).

| Method | Authorize | FormRequest | Service call | Returns |
|--------|-----------|-------------|--------------|---------|
| `edit(Order $order)` | `update`, `$order` | — | — (controller eager-loads `customer`, `lines.serial.product`, `orderFees`; also loads `$addresses` like in `create()`) | `view('orders.edit', [order, sources, addresses])` |
| `update(UpdateOrderRequest, Order)` | `update`, `$order` | `UpdateOrderRequest` | `service->update($order, $req->validated(), $req->user())` — try/catch `DomainException` | redirect `orders.show` + flash `success` |
| `destroy(Order $order)` | `delete`, `$order` | — | `service->delete($order)` — try/catch `DomainException` | redirect `orders.index` + flash `success` |
| `cancel(Request, Order)` | `cancel`, `$order` | — (no body) | `service->cancel($order, $request->user())` — try/catch `DomainException` | redirect `orders.show` + flash `success` |

`DomainException` handling pattern (every action): `back()->withErrors(['error' => $e->getMessage()])->withInput()` (omit `withInput()` for cancel/destroy — no input to preserve).

---

## Section 9 — Views (delta)

### 9A — `resources/views/orders/edit.blade.php` (new)

> Same 2-column WooCommerce layout as `create.blade.php` (see [`03-controllers.md`](03-controllers.md) §6B). Key differences below.

#### Form

| Element | Value |
|---------|-------|
| `<form>` action | `route('orders.update', $order)` |
| `<form>` method | `POST` + `@method('PUT')` |

#### Differences from `create.blade.php`

| Section | Create | Edit |
|---------|--------|------|
| Customer | Tom Select | **Read-only** info card (avatar initial, name, email, phone) |
| Line Items | 3-step AJAX cascade | **Read-only** table — lines not editable |
| Fees | dynamic add/remove | dynamic add/remove (same) |
| Shipping amount | input | input |
| Billing tabs | none / saved / new (default `none`) | none / saved / new — default derived from existing snapshot |
| Shipping tabs | same / saved / new / none (default `same`) | same / saved / new / none — default derived from existing snapshot |
| Submit button | "Create Order" | "Save Changes" |

#### Alpine state (pre-populated from `$order`)

| Key | Initial value |
|-----|---------------|
| `fees` | `@json($order->orderFees->map(fn ($f) => ['name' => $f->name, 'amount' => $f->amount]))` — **passed via `window.__orderEditFees`**, NOT inline in `x-data` (per [`00-rules.md`](00-rules.md) §3) |
| `subtotal` | `parseFloat('{{ $order->subtotal }}')` — fixed (tax-inclusive, lines unchanged) |
| `shippingAmount` | `parseFloat('{{ old('shipping_amount', $order->shipping) }}')` |
| `shippingType` | `'{{ old('shipping_type', $order->shipping_address_line1 ? 'new' : 'none') }}'` |
| `billingType` | `'{{ old('billing_type',  $order->billing_address_line1  ? 'new' : 'none') }}'` |
| `selectedShippingId` | null |
| `selectedBillingId` | null |
| getter `feesTotal` | sum of `fees[].amount` |
| getter `grandTotal` | `subtotal + feesTotal + parseFloat(shippingAmount || 0)` — no separate tax |

> `billing_same_as_shipping` hidden input always present, `:value="billingType === 'same' ? '1' : ''"`.

#### New-address fields prepopulated

For shipping new fields: `old('shipping.first_name', $order->shipping_first_name)` etc.
For billing new fields: `old('billing.first_name',  $order->billing_first_name)` etc.

> Customer panel needs `window.__orderAddresses` for the saved-address tab (same as create). Pass via script block before Alpine.

---

### 9B — `resources/views/orders/show.blade.php` — add action buttons

> Add to header card. Spec already in [`03-controllers.md`](03-controllers.md) §6C "Header action buttons" — this section gives the Alpine pattern detail.

#### Edit button (Pending only)

| Element | Spec |
|---------|------|
| Guard | `@can('update', $order)` AND `$order->status === \App\Enums\OrderStatus::Pending` |
| Element | `<a>` link to `route('orders.edit', $order)` |
| Label | "Edit Order" |

#### Cancel button (Pending or Processing)

| Element | Spec |
|---------|------|
| Guard | `@can('cancel', $order)` AND `in_array($order->status, [Pending, Processing])` |
| Element | `<form method="POST" action="{{ route('orders.cancel', $order) }}" x-data @submit.prevent="if (confirm('Cancel this order?')) $el.submit()">` |
| Body | `@csrf` then `<button type="submit">Cancel Order</button>` |

#### Delete button (Cancelled only — super_admin)

| Element | Spec |
|---------|------|
| Guard | `@can('delete', $order)` AND `$order->status === Cancelled` |
| Element | `<form method="POST" action="{{ route('orders.destroy', $order) }}" x-data @submit.prevent="if (confirm('Permanently delete this order? This cannot be undone.')) $el.submit()">` |
| Body | `@csrf` then `@method('DELETE')` then `<button type="submit">Delete Order</button>` |

> Both destructive forms use Alpine `@submit.prevent` + JS `confirm()` — never native `onclick="return confirm(...)"`. Per `admin-views.md` "destructive actions" rule.

---

## Section 10 — Tests (delta)

> Helpers, setup, and matrix from [`04-tests.md`](04-tests.md) sections B + C + E apply.

### Unit — additions to `tests/Unit/OrderServiceTest.php`

#### `update()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 30 | updates source, shipping amount, fees on pending order | Pending order with 1 fee | `update(['source'=>'phone', 'shipping_amount'=>20, 'fees'=>[['name'=>'New','amount'=>5]], ...])` | source/shipping/fees updated in DB |
| 31 | recalculates grand_total on update | Pending order, subtotal=100 | update with fees=20, shipping=10 | `grand_total === '130.00'` |
| 32 | clears shipping snapshot when shipping type set to none | Pending order with shipping snapshot | update with empty `shipping` array | `shipping_first_name === null` (and all 9 other shipping cols) |
| 33 | updates shipping snapshot when new address given | Pending order | update with inline shipping | `shipping_first_name` and `shipping_address_line1` updated |
| 34 | deletes old fees and recreates on update | Pending order with old fee | update with new fee only | old fee gone, new fee in `order_fees` |
| 35 | throws DomainException when editing non-pending order | Processing order | update | throws `\DomainException` "Only pending orders can be edited." |

#### `cancel()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 36 | cancels a pending order | Pending order | cancel | status=Cancelled, cancelled_at set, cancelled_by === actor.id |
| 37 | cancels a processing order | Processing order | cancel | same as above |
| 38 | throws DomainException when cancelling shipped order | Shipped order | cancel | throws `\DomainException` "Only pending or processing orders can be cancelled." |
| 39 | throws DomainException when cancelling already-cancelled order | Cancelled order | cancel | same message |

#### `delete()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 40 | deletes a cancelled order, its lines, and fees | Cancelled order with line + fee | delete | order/lines/fees all gone (`assertDatabaseMissing`) |
| 41 | throws DomainException when deleting non-cancelled order | Pending order | delete | throws `\DomainException` "Only cancelled orders can be deleted." |
| 42 | does NOT delete payments when deleting order | Cancelled order with payment | delete | payment still in DB |

### Feature — additions to `tests/Feature/OrderControllerTest.php`

#### Update

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 43 | renders edit page for pending order | admin | GET `orders.edit` | `assertOk`, `assertViewIs('orders.edit')` |
| 44 | returns 403 on edit for sales role | sales | GET edit | `assertForbidden` |
| 45 | redirects to show on successful update | admin | PUT `orders.update` valid payload | `assertRedirect(route('orders.show', $order))` + success flash |
| 46 | shows validation error when source missing | admin | PUT without source | `assertSessionHasErrors('source')` |
| 47 | shows error flash when editing non-pending order | admin | PUT update on Processing order | `assertSessionHasErrors('error')` |

#### Cancel

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 48 | cancels a pending order via controller | admin | POST `orders.cancel` | redirect to show + success flash + status=Cancelled |
| 49 | returns 403 on cancel for sales role | sales | POST cancel | `assertForbidden` |
| 50 | shows error when cancelling shipped order | admin | POST cancel on shipped order | `assertSessionHasErrors('error')` |

#### Delete

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 51 | deletes a cancelled order via controller | super_admin (full perms incl `orders.delete`) | DELETE `orders.destroy` on cancelled order | redirect to index + success flash + `assertDatabaseMissing('orders', ['id'=>$order->id])` |
| 52 | returns 403 on delete for admin (lacks orders.delete) | admin | DELETE on cancelled order | `assertForbidden` |
| 53 | shows error when deleting non-cancelled order | super_admin | DELETE on pending order | `assertSessionHasErrors('error')` |

---

## Section 11 — Data flow summary

```
── UPDATE ────────────────────────────────────────────────────
GET  /orders/{order}/edit
  edit()  → authorize('update') → eager-load → view('orders.edit')

PUT  /orders/{order}
  UpdateOrderRequest::authorize() + rules()
  update()
    → authorize('update')
    → service->update()
        transaction:
          guard status=Pending or DomainException
          resolveAddress(shipping) + resolveAddress(billing)
          orderFees()->delete() + recreate
          subtotal = $order->subtotal (unchanged)
          order->update(source, fees, shipping, grand_total, snapshots × 20)
          return fresh()
    → catch DomainException → back()->withErrors(['error' => ...])
    → redirect show + success

── CANCEL ────────────────────────────────────────────────────
POST /orders/{order}/cancel
  cancel()
    → authorize('cancel')
    → service->cancel()
        guard status in [Pending, Processing] or DomainException
        order->update(status=Cancelled, cancelled_at, cancelled_by)
        return fresh()
    → catch DomainException → back()->withErrors
    → redirect show + success

── DELETE ────────────────────────────────────────────────────
DELETE /orders/{order}
  destroy()
    → authorize('delete')
    → service->delete()
        transaction:
          guard status=Cancelled or DomainException
          orderFees()->delete()
          lines()->delete()
          order->delete()
    → catch DomainException → back()->withErrors
    → redirect index + success
```

---

## Section 12 — Implementation Checklist

### Shared prerequisites (do first)
- [ ] `routes/web.php` — add `edit`/`update`/`destroy`/`cancel` to existing prefix/group (after `deliver`)
- [ ] `OrderPermissionSeeder` — add `orders.update`, `orders.cancel`, `orders.delete`; **fix** admin grant to exclude `orders.delete`; ensure `manager` + `sales` grants
- [ ] `OrderPolicy` — add `update()`, `cancel()`, `delete()` methods
- [ ] `OrderService::shippingSnapshot()` + `billingSnapshot()` — accept `?CustomerAddress`, null-safe `?->`; remove outer ternary in `create()`

### Update
- [ ] `UpdateOrderRequest` — new file
- [ ] `OrderService::update()` — new method
- [ ] `OrderController::edit()` + `update()` — new methods
- [ ] `resources/views/orders/edit.blade.php` — new view (per §9A)
- [ ] `resources/views/orders/show.blade.php` — add Edit button

### Cancel
- [ ] `OrderService::cancel()` — new method
- [ ] `OrderController::cancel()` — new method
- [ ] `resources/views/orders/show.blade.php` — add Cancel button + Alpine confirm

### Delete
- [ ] `OrderService::delete()` — new method
- [ ] `OrderController::destroy()` — new method
- [ ] `resources/views/orders/show.blade.php` — add Delete button + Alpine confirm

### Tests
- [ ] `OrderServiceTest` — 13 new unit tests (tests 30–42)
- [ ] `OrderControllerTest` — 11 new feature tests (tests 43–53)

### Cleanup
- [ ] Browser-verify edit form (per [`00-rules.md`](00-rules.md) §8)
- [ ] Browser-verify cancel and delete buttons appear only with correct status × role combos
- [ ] `STATUS.md` — update order row after completion

---

**Reference:** [`skills/references/controller.md`](../../skills/references/controller.md) · [`skills/references/service.md`](../../skills/references/service.md) · [`skills/references/form-request.md`](../../skills/references/form-request.md) · [`skills/references/admin-views.md`](../../skills/references/admin-views.md) · [`skills/references/testing.md`](../../skills/references/testing.md).
