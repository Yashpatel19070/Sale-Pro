# InventoryLocation Module — Overview

## Purpose

`InventoryLocation` defines the physical warehouse locations (shelves, bins, zones)
where stock lives. It is the **foundation module** for the entire inventory system —
every stock movement references a location by FK. Nothing else in the inventory system
can be built until this module is complete.

A location has a short machine code (e.g. `L1`, `ZONE-A`) and a human-readable name
(e.g. `Shelf L1 Row A`). Soft delete is "deactivation" — a location with active stock
on it cannot be deactivated.

---

## Dependency Diagram (ASCII)

```
  ┌───────────────────────────────────────────────────────┐
  │                  EXISTING MODULES                     │
  │   User ─── AuditLog (spatie/activitylog)              │
  └───────────────────────────────────────────────────────┘
                           │
                           ▼
  ┌─────────────────────────────────────────────────────────┐
  │              InventoryLocation  (THIS MODULE)           │
  │  code, name, description, is_active, soft delete        │
  │  + LogsActivity trait                                   │
  └─────────────────────────────────────────────────────────┘
            │ FK: from_location_id          │ FK: to_location_id
            ▼                              ▼
  ┌─────────────────────────────────────────────────────────┐
  │          InventoryMovement  (FUTURE MODULE)             │
  │  product_id, from_location_id, to_location_id,          │
  │  type, quantity, reference, notes, user_id              │
  └─────────────────────────────────────────────────────────┘
            │ SUM(qty) grouped by product + location
            ▼
  ┌─────────────────────────────────────────────────────────┐
  │     inventory_stock (SQL VIEW — FUTURE MODULE)          │
  │     product_id, location_id, qty_on_hand                │
  └─────────────────────────────────────────────────────────┘
```

---

## Prerequisites
- None — this is the base module with no dependencies

---

## Features (V1)

| # | Feature | Description |
|---|---------|-------------|
| 1 | List locations | Paginated table, search by code or name, filter active/inactive |
| 2 | View location | Detail page showing all fields + count of serials currently on it |
| 3 | Create location | Form to create a new location (code must be unique) |
| 4 | Edit location | Form to update an existing location's name/description |
| 5 | Deactivate | Soft delete — blocked if location has active serials on it |
| 6 | Restore | Re-activates a soft-deleted location |

---

## Role Access Matrix

| Permission | admin | manager | sales |
|------------|:-----:|:-------:|:-----:|
| List | ✅ | ✅ | ✅ |
| View | ✅ | ✅ | ✅ |
| Create | ✅ | ✅ | ❌ |
| Edit | ✅ | ✅ | ❌ |
| Deactivate | ✅ | ✅ | ❌ |
| Restore | ✅ | ✅ | ❌ |

---

## File Map

| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_inventory_locations_table.php` |
| Model | `app/Models/InventoryLocation.php` |
| Service | `app/Services/InventoryLocationService.php` |
| Controller | `app/Http/Controllers/InventoryLocationController.php` |
| Store Request | `app/Http/Requests/Inventory/StoreInventoryLocationRequest.php` |
| Update Request | `app/Http/Requests/Inventory/UpdateInventoryLocationRequest.php` |
| Policy | `app/Policies/InventoryLocationPolicy.php` |
| View: index | `resources/views/inventory/locations/index.blade.php` |
| View: show | `resources/views/inventory/locations/show.blade.php` |
| View: create | `resources/views/inventory/locations/create.blade.php` |
| View: edit | `resources/views/inventory/locations/edit.blade.php` |
| View: _form | `resources/views/inventory/locations/_form.blade.php` |
| Permission Seeder | `database/seeders/InventoryLocationPermissionSeeder.php` |
| Data Seeder | `database/seeders/InventoryLocationSeeder.php` |
| Factory | `database/factories/InventoryLocationFactory.php` |
| Feature Test | `tests/Feature/InventoryLocationControllerTest.php` |
| Unit Test | `tests/Unit/Services/InventoryLocationServiceTest.php` |

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add `INVENTORY_LOCATIONS_*` constants |
| `app/Providers/AppServiceProvider.php` | Register `InventoryLocationPolicy` via `Gate::policy()` |
| `routes/web.php` | Add inventory-locations routes inside admin middleware group |
| `database/seeders/DatabaseSeeder.php` | Call `InventoryLocationPermissionSeeder` + `InventoryLocationSeeder` |

---

## Implementation Order

1. Migration → `php artisan migrate`
2. Model (`InventoryLocation`)
3. Factory (`InventoryLocationFactory`)
4. Service (`InventoryLocationService`)
5. FormRequests (`StoreInventoryLocationRequest`, `UpdateInventoryLocationRequest`)
6. Policy (`InventoryLocationPolicy`)
7. Register policy in `AppServiceProvider`
8. Add permission constants to `Permission` enum
9. Controller (`InventoryLocationController`)
10. Routes (add to `routes/web.php`)
11. Views (index → show → create → edit → _form)
12. Seeders (`InventoryLocationPermissionSeeder`, `InventoryLocationSeeder`)
13. Tests (feature → unit)

---

## Key Rules — NEVER break these

- `strict_types=1` on every PHP file
- `$request->validated()` always — never `$request->all()`
- `with()` always for eager loading — never lazy load
- `$this->authorize()` on every controller action
- Soft delete only — `deactivate()` calls `$location->delete()`, never `forceDelete()`
- `code` must be unique among non-deleted locations (`Rule::unique()->withoutTrashed()` — soft-deleted codes CAN be reused; remove `withoutTrashed()` to block permanently)
- Cannot deactivate a location that has active serials on it — service must check before deleting
- `restore()` re-activates by calling `$location->restore()` — sets `deleted_at = null`
- `scopeActive()` is the canonical scope for dropdown lists in other modules
- `LogsActivity` trait on model — Spatie activity log records all changes automatically
- Every controller action has a Pest feature test
- Every service method has a Pest unit test
