# InventorySerial — FormRequests & Policy

## StoreInventorySerialRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\InventorySerial;

use App\Models\InventorySerial;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventorySerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventorySerial::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('serial_number')) {
            $this->merge([
                'serial_number' => strtoupper(trim($this->input('serial_number'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'product_id'             => ['required', 'integer', 'exists:products,id'],
            'inventory_location_id'  => ['required', 'integer', 'exists:inventory_locations,id'],
            'serial_number'          => ['required', 'string', 'max:100', 'unique:inventory_serials,serial_number'],
            'purchase_price'         => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'received_at'            => ['required', 'date', 'before_or_equal:today'],
            'supplier_name'          => ['nullable', 'string', 'max:150'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.unique'          => 'This serial number already exists in the system.',
            'received_at.before_or_equal'   => 'Received date cannot be in the future.',
            'inventory_location_id.exists'  => 'The selected shelf location does not exist.',
        ];
    }
}
```

**File path:** `app/Http/Requests/InventorySerial/StoreInventorySerialRequest.php`

---

## UpdateInventorySerialRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\InventorySerial;

use App\Models\InventorySerial;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventorySerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InventorySerial $serial */
        $serial = $this->route('inventorySerial');

        return $this->user()->can('update', $serial);
    }

    public function rules(): array
    {
        // IMPORTANT: serial_number and purchase_price are intentionally absent.
        // They are immutable after creation and must never appear here.
        return [
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

**File path:** `app/Http/Requests/InventorySerial/UpdateInventorySerialRequest.php`

---

## InventorySerialPolicy.php

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventorySerial;
use App\Models\User;

class InventorySerialPolicy
{
    /**
     * List serials — admin, manager, sales.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_VIEW_ANY);
    }

    /**
     * View a single serial — admin, manager, sales.
     */
    public function view(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_VIEW);
    }

    /**
     * Receive (create) a new serial — admin, manager, sales.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_CREATE);
    }

    /**
     * Edit notes / supplier_name — admin, manager, sales.
     */
    public function update(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_EDIT);
    }

    /**
     * Mark as damaged — admin, manager only (not sales).
     */
    public function markDamaged(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_MARK_DAMAGED);
    }

    /**
     * Mark as missing — admin, manager only (not sales).
     */
    public function markMissing(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_MARK_MISSING);
    }

    /**
     * View purchase price — admin and manager only (internal cost data, hidden from sales).
     */
    public function viewPurchasePrice(User $user, InventorySerial $serial): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }
}
```

**File path:** `app/Policies/InventorySerialPolicy.php`

---

## Permission Constants to Add to app/Enums/Permission.php

```php
// Inventory Serials
const INVENTORY_SERIALS_VIEW_ANY     = 'inventory-serials.view-any';
const INVENTORY_SERIALS_VIEW         = 'inventory-serials.view';
const INVENTORY_SERIALS_CREATE       = 'inventory-serials.create';
const INVENTORY_SERIALS_EDIT         = 'inventory-serials.edit';
const INVENTORY_SERIALS_MARK_DAMAGED = 'inventory-serials.mark-damaged';
const INVENTORY_SERIALS_MARK_MISSING = 'inventory-serials.mark-missing';
```

These constants go in `app/Enums/Permission.php` after the existing `PRODUCT_LISTINGS_*` block.

---

## Policy Registration in AppServiceProvider

Add to the `$policies` array in `app/Providers/AppServiceProvider.php`:

```php
\App\Models\InventorySerial::class => \App\Policies\InventorySerialPolicy::class,
```

Or, if the project uses `Gate::policy()` in a `boot()` method:

```php
Gate::policy(\App\Models\InventorySerial::class, \App\Policies\InventorySerialPolicy::class);
```

---

## Design Notes

### prepareForValidation — serial_number normalization
Serial numbers are uppercased and trimmed before uniqueness validation. This prevents
`sn-00001` and `SN-00001` from both passing the unique check when they represent the same
physical item.

### UpdateInventorySerialRequest — intentional omissions
`serial_number` and `purchase_price` are deliberately absent from the update rules. This is
the enforcement boundary for immutability — if a future developer adds these fields to the
form, they will be silently ignored by `$request->validated()`.

### markDamaged / markMissing separate permissions
These are split into two separate permission constants (`MARK_DAMAGED` vs `MARK_MISSING`)
rather than a single `MARK_STATUS` constant. This preserves the option to grant one without
the other in future without a schema change.

---

## Developer Checklist — Before Marking Complete

### PHP & Code Style
- [ ] `declare(strict_types=1)` on every PHP file (model, service, controller, requests, policy, factory, seeder)
- [ ] Full type hints on every method — no missing return types or parameter types
- [ ] No raw permission strings anywhere — always `Permission::CONSTANT`

### Database & Model
- [ ] Migration has NO `softDeletes()` — serials are never deleted, only status-changed
- [ ] `$fillable` set to: `['product_id', 'inventory_location_id', 'serial_number', 'status', 'purchase_price', 'received_at', 'received_by_user_id', 'notes']`
- [ ] `casts()` returns `['status' => SerialStatus::class, 'purchase_price' => 'decimal:2', 'received_at' => 'datetime']`
- [ ] `product()`, `location()`, `receivedBy()`, `movements()` relations defined and typed
- [ ] `movements()` relation only added after inventory-movement module is built
- [ ] `inStock()` scope filters `where('status', SerialStatus::InStock)`
- [ ] `LogsActivity` trait present; `logExcept` excludes `['purchase_price', 'inventory_location_id', 'status']` — those are tracked by movement ledger

### Service Layer
- [ ] `receive()` creates serial with status=InStock AND creates a movement row (type=Receive) in the same `DB::transaction()`
- [ ] Movement row in `receive()` has NO `product_id` field — derived via serial relation
- [ ] `updateNotes()` is the only update method — serial_number and purchase_price are immutable
- [ ] `markDamaged()` and `markMissing()` do NOT exist on this service — status changes via `InventoryMovementService::adjustment()`
- [ ] All queries use `with()` — no N+1 (e.g., `with(['product', 'location'])` on list/show)
- [ ] `list()` and `show()` paginate appropriately — `paginate(15)` for lists

### FormRequest
- [ ] `StoreInventorySerialRequest::authorize()` delegates to `$this->user()->can('create', InventorySerial::class)`
- [ ] `UpdateInventorySerialRequest::authorize()` delegates to `$this->user()->can('update', $serial)`
- [ ] `serial_number` and `purchase_price` are ABSENT from `UpdateInventorySerialRequest::rules()` — immutability enforced here
- [ ] `$request->validated()` used in controller — never `$request->all()`
- [ ] `prepareForValidation()` uppercases and trims serial_number before uniqueness check

### Controller
- [ ] `index()` calls `$this->authorize('viewAny', InventorySerial::class)`
- [ ] `create()` calls `$this->authorize('create', InventorySerial::class)`
- [ ] `store()` calls `$this->authorize('create', InventorySerial::class)`
- [ ] `show()` calls `$this->authorize('view', $inventorySerial)`
- [ ] `edit()` calls `$this->authorize('update', $inventorySerial)`
- [ ] `update()` calls `$this->authorize('update', $inventorySerial)`
- [ ] Constructor injects both `InventorySerialService` and `InventoryLocationService`
- [ ] `show()` loads movement history as a separate paginated query — not via eager load on the serial
- [ ] NO `markDamaged` or `markMissing` actions on this controller

### Policy & Permissions
- [ ] 6 permission constants in `Permission` enum: `INVENTORY_SERIALS_VIEW_ANY`, `VIEW`, `CREATE`, `EDIT`, `MARK_DAMAGED`, `MARK_MISSING`
- [ ] `viewPurchasePrice()` policy method checks `hasRole(['admin', 'manager'])` — not a permission constant (UI-only gate)
- [ ] `InventorySerialPolicy` registered in `AppServiceProvider` via `Gate::policy()`
- [ ] `sales` role gets VIEW_ANY, VIEW, CREATE only — not EDIT, MARK_DAMAGED, MARK_MISSING

### Views
- [ ] `@csrf` on store and update forms
- [ ] `@method('PUT')` on update form
- [ ] All output uses `{{ }}` — never `{!! !!}`
- [ ] Edit form shows `serial_number` and `purchase_price` as read-only display text — not input fields
- [ ] `purchase_price` wrapped in `@can('viewPurchasePrice', $serial) ... @endcan`
- [ ] "Record Adjustment" link: `route('inventory-movements.create', ['serial_id' => $serial->id, 'type' => 'adjustment'])` — only shown when serial is in_stock
- [ ] Nullable location displayed as `{{ $serial->location?->code ?? '—' }}`

### Routes & Seeders
- [ ] 6 routes registered: index, create, store, show, edit, update — NO destroy, markDamaged, markMissing
- [ ] `InventorySerialPermissionSeeder` runs after `RoleSeeder` and `InventoryLocationPermissionSeeder`
- [ ] Seeder uses null-safe `Role::where('name', ...)->first()?->givePermissionTo()`

### Tests
- [ ] Feature test for every controller action: `index`, `show`, `create`, `store`, `edit`, `update`
- [ ] Each feature test covers: happy path + unauthenticated redirect + authorization failure
- [ ] Feature test: `store()` verifies a `receive` movement row is created automatically
- [ ] Feature test: duplicate serial_number for same product fails validation
- [ ] Feature test: `sales` user cannot see `purchase_price` in show response
- [ ] Feature test: `admin`/`manager` can see `purchase_price`
- [ ] Unit test: `receive()` — serial created with status=InStock, movement row created, same transaction
- [ ] Unit test: `updateNotes()` — only notes field changes; serial_number unchanged
- [ ] `RefreshDatabase` trait on every test class
- [ ] All test data via factories — no hardcoded IDs or values
- [ ] Factory states: `inStock()`, `sold()`, `damaged()`, `missing()`, `forProduct($p)`, `atLocation($l)`

