# InventoryLocation Module — Requests & Policy

---

## 1. StoreInventoryLocationRequest

**File:** `app/Http/Requests/Inventory/StoreInventoryLocationRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('inventory_locations', 'code')->withoutTrashed(),
            ],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The code may only contain letters, numbers, hyphens, and underscores.',
            'code.unique' => 'This location code is already in use.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
```

---

## 2. UpdateInventoryLocationRequest

**File:** `app/Http/Requests/Inventory/UpdateInventoryLocationRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
    }

    public function rules(): array
    {
        // 'code' is intentionally absent — code is immutable after creation.
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## 3. InventoryLocationPolicy

**File:** `app/Policies/InventoryLocationPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryLocation;
use App\Models\User;

class InventoryLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_VIEW_ANY);
    }

    public function view(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_CREATE);
    }

    public function update(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_EDIT);
    }

    public function delete(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_DELETE);
    }

    public function restore(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_RESTORE);
    }
}
```

---

## 4. Permission Constants (add to `app/Enums/Permission.php`)

Add this block after the existing `PRODUCT_LISTINGS_*` constants:

```php
// Inventory Locations
const INVENTORY_LOCATIONS_VIEW_ANY = 'inventory-locations.view-any';
const INVENTORY_LOCATIONS_VIEW     = 'inventory-locations.view';
const INVENTORY_LOCATIONS_CREATE   = 'inventory-locations.create';
const INVENTORY_LOCATIONS_EDIT     = 'inventory-locations.edit';
const INVENTORY_LOCATIONS_DELETE   = 'inventory-locations.delete';
const INVENTORY_LOCATIONS_RESTORE  = 'inventory-locations.restore';
```

---

## 5. Register Policy (in `app/Providers/AppServiceProvider.php`)

Add inside the `boot()` method alongside the existing `Gate::policy()` calls:

```php
Gate::policy(\App\Models\InventoryLocation::class, \App\Policies\InventoryLocationPolicy::class);
```

---

## Notes on Validation

- `code` uses `Rule::unique()->withoutTrashed()` — uniqueness is checked among non-deleted
  locations only. A soft-deleted location's code CAN be reused by a new record. Remove
  `withoutTrashed()` if you want to permanently block reuse of retired location codes.
- `prepareForValidation()` in `StoreInventoryLocationRequest` uppercases + trims the code
  before validation, so the unique rule runs on the normalized value.
- `UpdateInventoryLocationRequest` deliberately omits `code` — the service also ignores it
  even if somehow passed. Code is set once at creation and never changed.
- `description` is `nullable` with a `max:1000` guard to prevent abuse.
- The `regex` rule on `code` ensures only URL-safe characters: letters, digits, `-`, `_`.

## Developer Checklist — Before Marking Complete

### PHP & Code Style
- [ ] `declare(strict_types=1)` on every PHP file (model, service, controller, requests, policy, seeder)
- [ ] Full type hints on every method — no missing return types or parameter types
- [ ] No raw permission strings anywhere — always `Permission::CONSTANT`

### Database & Model
- [ ] Migration includes `softDeletes()` column
- [ ] `InventoryLocation` model has `SoftDeletes` trait
- [ ] `$fillable` set to `['code', 'name', 'description', 'is_active']`
- [ ] `casts()` returns `['is_active' => 'boolean']`
- [ ] `active()` scope filters `where('is_active', true)`

### Service Layer
- [ ] `list()` uses `InventoryLocation::withoutTrashed()` — not `::query()` (excludes soft-deleted)
- [ ] `activeForDropdown()` returns only active locations ordered by code
- [ ] `deactivate()` guard runs `activeSerialCount()` before soft-deleting
- [ ] `restore()` accepts the model, not an int ID
- [ ] No multi-table writes in this module — no `DB::transaction()` needed (single-table)

### FormRequest
- [ ] `StoreInventoryLocationRequest` — code required + unique `withoutTrashed` + regex; name required
- [ ] `UpdateInventoryLocationRequest` — code field absent (immutable after creation); name + description only
- [ ] Both requests delegate `authorize()` to the Policy via `$this->user()->can()`
- [ ] `$request->validated()` used in controller — never `$request->all()`

### Controller
- [ ] Every action calls `$this->authorize()` using the Policy
- [ ] `restore()` resolves model with `InventoryLocation::withTrashed()->findOrFail($id)` — NOT route model binding (which excludes soft-deleted)
- [ ] `destroy()` catches `\DomainException` and returns `back()->withErrors(['error' => $e->getMessage()])`
- [ ] Controller injects `InventoryLocationService` via constructor — no `new Service()` calls

### Policy & Permissions
- [ ] 6 permission constants added to `Permission` enum: `INVENTORY_LOCATIONS_VIEW_ANY`, `VIEW`, `CREATE`, `EDIT`, `DELETE`, `RESTORE`
- [ ] `InventoryLocationPolicy` has 6 methods, each checking the correct `Permission::` constant
- [ ] Policy registered in `AppServiceProvider` via `Gate::policy(InventoryLocation::class, InventoryLocationPolicy::class)`

### Views
- [ ] `@csrf` on every `<form>` (store, update, destroy, restore)
- [ ] `@method('PUT')` on update form; `@method('DELETE')` on destroy form; `@method('POST')` on restore form
- [ ] All output uses `{{ }}` — never `{!! !!}`
- [ ] Code field rendered as read-only text on the edit form (not an input)
- [ ] Soft-deleted locations not shown in the active list or any dropdown

### Routes & Seeders
- [ ] 8 routes registered in the admin middleware group: index, create, store, show, edit, update, destroy, restore
- [ ] `restore` route uses plain `{id}` (not `{inventoryLocation}`) to bypass route model binding
- [ ] `InventoryLocationPermissionSeeder` runs after `RoleSeeder`
- [ ] Seeder uses `Role::where('name', ...)->first()?->givePermissionTo()` — null-safe (no firstOrFail)

### Tests
- [ ] Feature test for every controller action (8 tests minimum: index, show, create, store, edit, update, destroy, restore)
- [ ] Each feature test covers: happy path + auth failure (unauthenticated) + authorization failure (wrong role)
- [ ] Unit test for every service method: `list`, `store`, `update`, `deactivate`, `restore`, `activeForDropdown`, `activeSerialCount`
- [ ] `RefreshDatabase` trait on every test class
- [ ] `deactivate()` unit test: blocked when location has active serials (guard test)
- [ ] `deactivate()` unit test: succeeds when location has no in_stock serials
- [ ] All test data created via factories — no hardcoded IDs or values
