# InventorySerial Module — Overview

## Business Context

An `InventorySerial` represents one physical unit of a product, identified by a unique serial number.
Every item that arrives in the warehouse is one row in `inventory_serials`.

This is the central model of the serial-tracking inventory system. Unlike the movement-ledger approach
in the base Inventory module (which tracks aggregate qty), the serial module tracks individual units.

```
Product: WIDGET-001
  ├── Serial #SN-00001  → Shelf L1  (in_stock)
  ├── Serial #SN-00002  → Shelf L1  (in_stock)
  ├── Serial #SN-00003  → null      (sold)
  └── Serial #SN-00004  → null      (damaged)
```

Receiving a serial always creates a corresponding `InventoryMovement` row (type: `receive`)
so the movement ledger stays consistent with serial-level tracking.

---

## Module Boundary

Admin-only. No customer-portal exposure.

---

## Prerequisites
- `inventory-location` module must be fully migrated before this module
- InventoryLocation model must exist (FK constraint on inventory_location_id)

---

## Dependency Diagram

```
Product (existing)
  └── InventorySerial ──▶ InventoryLocation (inventory module)
                      ──▶ User (received_by_user_id)
                      ──▶ InventoryMovement (auto-created on receive)
                      ──▶ AuditLog (LogsActivity)
```

---

## Features (V1)

| # | Feature | Roles |
|---|---------|-------|
| 1 | List serials — paginated, search serial/SKU, filter status/location/product | admin, manager, sales |
| 2 | View serial — full detail + movement history | admin, manager, sales |
| 3 | Receive serial — create new serial + InventoryMovement(receive) | admin, manager, sales |
| 4 | Edit serial — update notes and supplier_name only | admin, manager, sales |
| 5 | Mark as damaged | admin, manager |
| 6 | Mark as missing | admin, manager |
| 7 | Quick search by serial number | admin, manager, sales |

---

## Role Access Matrix

| Permission | admin | manager | sales |
|------------|:-----:|:-------:|:-----:|
| List | ✅ | ✅ | ✅ |
| View | ✅ | ✅ | ✅ |
| Receive (create) | ✅ | ✅ | ✅ |
| Edit notes | ✅ | ✅ | ✅ |
| Mark damaged/missing | ✅ | ✅ | ❌ |

---

## File Map

| File | Path |
|------|------|
| Migration: serials | `database/migrations/xxxx_create_inventory_serials_table.php` |
| Enum: SerialStatus | `app/Enums/SerialStatus.php` |
| Model | `app/Models/InventorySerial.php` |
| Factory | `database/factories/InventorySerialFactory.php` |
| Service | `app/Services/InventorySerialService.php` |
| Controller | `app/Http/Controllers/InventorySerialController.php` |
| Request: Store | `app/Http/Requests/InventorySerial/StoreInventorySerialRequest.php` |
| Request: Update | `app/Http/Requests/InventorySerial/UpdateInventorySerialRequest.php` |
| Policy | `app/Policies/InventorySerialPolicy.php` |
| View: index | `resources/views/inventory/serials/index.blade.php` |
| View: show | `resources/views/inventory/serials/show.blade.php` |
| View: create | `resources/views/inventory/serials/create.blade.php` |
| View: edit | `resources/views/inventory/serials/edit.blade.php` |
| View: _form | `resources/views/inventory/serials/_form.blade.php` |
| Permission Seeder | `database/seeders/InventorySerialPermissionSeeder.php` |
| Data Seeder | `database/seeders/InventorySerialSeeder.php` |
| Feature Test | `tests/Feature/InventorySerialControllerTest.php` |
| Unit Test | `tests/Unit/Services/InventorySerialServiceTest.php` |

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add `INVENTORY_SERIALS_*` permission constants |
| `app/Models/Product.php` | Add `serials(): HasMany` relationship |
| `app/Providers/AppServiceProvider.php` | Register `InventorySerialPolicy` |
| `routes/web.php` | Add `inventory-serials` resource routes inside admin group |
| `database/seeders/DatabaseSeeder.php` | Call `InventorySerialPermissionSeeder` and `InventorySerialSeeder` |

---

## Implementation Order

1. Schema — migration + `SerialStatus` enum → `php artisan migrate`
2. Model — `InventorySerial` with relationships and scopes; add `serials()` to `Product`
3. Factory — `InventorySerialFactory`
4. Service — `InventorySerialService`
5. FormRequests — `StoreInventorySerialRequest`, `UpdateInventorySerialRequest`
6. Policy — `InventorySerialPolicy` + add permission constants to `Permission` enum
7. Controller — `InventorySerialController`
8. Routes
9. Views
10. Seeders
11. Tests

---

## Key Rules

- `serial_number` must be unique across all products (global uniqueness, not per-product)
- `purchase_price` and `serial_number` are immutable after creation — never in update form or `UpdateInventorySerialRequest`
- Receiving a serial automatically creates an `InventoryMovement` row (type: `receive`) — always inside `DB::transaction`
- `inventory_location_id` is updated by `InventoryMovement` operations, not directly edited by this module
- Eager load `product` and `location` (and `receivedBy` on show) on all queries — never lazy load
- `$request->validated()` always — never `$request->all()`
- `strict_types=1` on every PHP file
- Every controller action has a Pest feature test
- Every service method has a Pest unit test
- Roles in this project: `super-admin` (future/null-safe), `admin`, `manager`, `sales` (NOT `staff`)
