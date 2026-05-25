# TDD-Order — Permissions

> Authoritative permission matrix for the order module.
> Every policy method, seeder entry, and test assertion derives from this file.

---

## Permission strings (database values)

| String | Purpose |
|---|---|
| `orders.viewAny` | List all orders (index) |
| `orders.view` | View one order (show) |
| `orders.create` | Create new order |
| `orders.pay` | Record cash payment |
| `orders.ship` | Mark order shipped |
| `orders.deliver` | Mark order delivered |
| `orders.update` | Edit order (before processing) |
| `orders.cancel` | Cancel pending/processing order |
| `orders.delete` | Delete cancelled order |

---

## Role assignment matrix

| Permission | super_admin | admin | manager | sales |
|---|---|---|---|---|
| `orders.viewAny` | ✓ | ✓ | ✓ | ✓ |
| `orders.view` | ✓ | ✓ | ✓ | ✓ |
| `orders.create` | ✓ | ✓ | ✓ | — |
| `orders.pay` | ✓ | ✓ | ✓ | — |
| `orders.ship` | ✓ | ✓ | ✓ | — |
| `orders.deliver` | ✓ | ✓ | ✓ | — |
| `orders.update` | ✓ | ✓ | ✓ | — |
| `orders.cancel` | ✓ | ✓ | ✓ | — |
| `orders.delete` | ✓ | — | — | — |

**Rules:**
- `sales` role: view-only (viewAny + view only)
- `admin` role: all except delete
- `manager` role: same as admin (all except delete)
- `super_admin` role: all permissions

---

## Policy class: `App\Policies\OrderPolicy`

| Policy method | Permission checked | Gate name |
|---|---|---|
| `viewAny` | `orders.viewAny` | `viewAny` |
| `view` | `orders.view` | `view` |
| `create` | `orders.create` | `create` |
| `pay` | `orders.pay` | `pay` |
| `ship` | `orders.ship` | `ship` |
| `deliver` | `orders.deliver` | `deliver` |
| `update` | `orders.update` | `update` |
| `cancel` | `orders.cancel` | `cancel` |
| `delete` | `orders.delete` | `delete` |

Each method signature: `public function <method>(User $user[, Order $order]): bool`
- `viewAny` and `create` take only `User $user`
- All others take `User $user, Order $order`
- Body: `return $user->can('orders.<permission>');` — except `update` (see below)

**`update` policy exception:** Also checks order status.
Body: `return $user->can('orders.update') && $order->status === OrderStatus::Pending;`
Reason: edit form must never render for non-pending orders — block at policy, not at save.

---

## Seeder: `OrderPermissionSeeder`

**Order of operations:**
1. Create all 9 permissions via `Permission::firstOrCreate(['name' => '...', 'guard_name' => 'web'])`
2. Assign to roles using `Role::where('name', '...')->first()?->givePermissionTo([...])`

**`$all`** = all 9 permissions (for super_admin)
**`$adminPerms`** = all except `orders.delete` (8 permissions, for admin and manager)
**`$viewOnly`** = [`orders.viewAny`, `orders.view`] (for sales)

**Role assignment:**
- `super_admin`: `Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($all)`
- `admin`: `Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($adminPerms)`
- `manager`: `Role::where('name', 'manager')->first()?->givePermissionTo($adminPerms)`
- `sales`: `Role::where('name', 'sales')->first()?->givePermissionTo($viewOnly)`

**Note:** `super_admin` and `admin` are created here if missing. `manager` and `sales` use `?->` — created by RoleSeeder, not here.

---

## Seeder registration

`OrderPermissionSeeder` must appear in `DatabaseSeeder::run()` **after** `RoleSeeder`.

---

## Prohibited patterns

- Never check permissions inside a service method — only in policy or controller/request
- Never use `$user->hasRole('admin')` for order permissions — use `$user->can('orders.X')`
- Never hard-code role names in OrderPolicy — delegate to `$user->can()`
