# InventoryMovement Module — FormRequest & Policy

## StoreInventoryMovementRequest

```php
<?php
// app/Http/Requests/Inventory/StoreInventoryMovementRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryMovementRequest extends FormRequest
{
    /**
     * Authorization: check the specific permission for the requested movement type.
     *
     * - transfer → INVENTORY_MOVEMENTS_TRANSFER
     * - sale     → INVENTORY_MOVEMENTS_SELL
     * - adjustment → INVENTORY_MOVEMENTS_ADJUST (admin/manager only)
     * - receive  → never permitted via UI
     */
    public function authorize(): bool
    {
        $type = $this->input('type');

        return match ($type) {
            MovementType::Transfer->value  => $this->user()->can(Permission::INVENTORY_MOVEMENTS_TRANSFER),
            MovementType::Sale->value      => $this->user()->can(Permission::INVENTORY_MOVEMENTS_SELL),
            MovementType::Adjustment->value => $this->user()->can(Permission::INVENTORY_MOVEMENTS_ADJUST),
            default => false, // receive and unknown types blocked
        };
    }

    /**
     * Validation rules.
     *
     * Core fields are always required.
     * Conditional fields depend on `type`:
     *   - transfer:   from_location_id + to_location_id required, others prohibited
     *   - sale:       sale_location_id required, from/to/adjustment prohibited
     *   - adjustment: adjustment_status required, locations prohibited
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'inventory_serial_id' => ['required', 'integer', 'exists:inventory_serials,id'],
            'type'                => [
                'required',
                'string',
                Rule::notIn([MovementType::Receive->value]), // receive is internal only
                Rule::in(array_column(MovementType::cases(), 'value')),
            ],

            // transfer — both locations required
            'from_location_id' => [
                Rule::when(
                    $type === MovementType::Transfer->value,
                    ['required', 'integer', 'exists:inventory_locations,id'],
                    ['prohibited']
                ),
            ],
            'to_location_id' => [
                Rule::when(
                    $type === MovementType::Transfer->value,
                    ['required', 'integer', 'exists:inventory_locations,id', 'different:from_location_id'],
                    ['prohibited']
                ),
            ],

            // sale — from location required, no to location
            'sale_location_id' => [
                Rule::when(
                    $type === MovementType::Sale->value,
                    ['required', 'integer', 'exists:inventory_locations,id'],
                    ['prohibited']
                ),
            ],

            // adjustment — status required, no locations
            'adjustment_status' => [
                Rule::when(
                    $type === MovementType::Adjustment->value,
                    ['required', 'string', Rule::in(['damaged', 'missing'])],
                    ['prohibited']
                ),
            ],

            'reference' => ['nullable', 'string', 'max:150'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_serial_id.required' => 'Please select a serial number.',
            'inventory_serial_id.exists'   => 'The selected serial number does not exist.',
            'type.required'                => 'Please select a movement type.',
            'type.not_in'                  => 'Receive movements cannot be recorded manually.',
            'from_location_id.required'    => 'A source location is required for this movement type.',
            'from_location_id.exists'      => 'The selected source location does not exist.',
            'to_location_id.required'      => 'A destination location is required for transfer.',
            'to_location_id.exists'        => 'The selected destination location does not exist.',
            'adjustment_status.required'   => 'Please select an adjustment status (damaged or missing).',
            'adjustment_status.in'         => 'Adjustment status must be "damaged" or "missing".',
            'adjustment_status.prohibited' => 'Adjustment status is only used for adjustment type movements.',
            'reference.max'                => 'Reference must be 150 characters or fewer.',
            'notes.max'                    => 'Notes must be 2000 characters or fewer.',
        ];
    }

    /**
     * Cross-field validation: serial must be in_stock, and from_location must match
     * the serial's current location for transfer and sale types.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $serial = InventorySerial::find($this->input('inventory_serial_id'));

                if (! $serial) return;

                // Serial must be in_stock for any movement type
                if ($serial->status !== SerialStatus::InStock) {
                    $validator->errors()->add(
                        'inventory_serial_id',
                        "Serial {$serial->serial_number} is not in stock (current status: {$serial->status->value})."
                    );
                    return;
                }

                // Transfer/sale: from_location must match serial's current location
                $fromField = $this->type === MovementType::Transfer->value
                    ? 'from_location_id'
                    : 'sale_location_id';

                if (in_array($this->input('type'), [MovementType::Transfer->value, MovementType::Sale->value])) {
                    $fromId = (int) $this->input($fromField);
                    if ($fromId !== (int) $serial->inventory_location_id) {
                        $validator->errors()->add(
                            $fromField,
                            "Serial {$serial->serial_number} is not at that location."
                        );
                    }
                }
            },
        ];
    }
}
```

---

## InventoryMovementPolicy

```php
<?php
// app/Policies/InventoryMovementPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    /**
     * View the movement history list.
     * Accessible by all roles (admin, manager, sales).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_VIEW);
    }

    /**
     * View a single movement record.
     */
    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_VIEW);
    }

    /**
     * Access the create form.
     * Any user who can transfer OR sell OR adjust can see the form.
     * The form itself shows/hides types based on the user's permissions.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_TRANSFER)
            || $user->can(Permission::INVENTORY_MOVEMENTS_SELL)
            || $user->can(Permission::INVENTORY_MOVEMENTS_ADJUST);
    }

    /**
     * Store a new movement.
     * The FormRequest's authorize() enforces per-type permissions.
     * This policy gate is the controller-level check before FormRequest fires.
     */
    public function store(User $user): bool
    {
        return $this->create($user);
    }

    /**
     * Update — NEVER allowed. Movements are immutable.
     */
    public function update(User $user, InventoryMovement $movement): bool
    {
        return false;
    }

    /**
     * Delete — NEVER allowed. Movements are immutable.
     */
    public function delete(User $user, InventoryMovement $movement): bool
    {
        return false;
    }
}
```

---

## AppServiceProvider Registration

```php
// app/Providers/AppServiceProvider.php — add to the $policies array

use App\Models\InventoryMovement;
use App\Policies\InventoryMovementPolicy;

// Inside register() or boot():
Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
```

---

## Permission Constants to Add in Permission Enum

```php
// app/Enums/Permission.php

const INVENTORY_MOVEMENTS_VIEW     = 'inventory-movements.view';
const INVENTORY_MOVEMENTS_TRANSFER = 'inventory-movements.transfer';
const INVENTORY_MOVEMENTS_SELL     = 'inventory-movements.sell';
const INVENTORY_MOVEMENTS_ADJUST   = 'inventory-movements.adjust';
```

---

## Design Notes

### Per-type authorization in FormRequest

The `authorize()` method checks the *specific* permission for the requested type, not a
single catch-all. This ensures `sales` role — which has `transfer` and `sell` but NOT `adjust` —
gets a clean 403 if they submit an adjustment form rather than a confusing validation error.
`manager` has the same permissions as `admin` (transfer, sell, adjust).

### `after()` for location/status cross-field check

Laravel's standard `rules()` can't express "field A must match a value from the DB row
resolved by field B". The `after()` hook runs after all standard rules pass, giving access
to the validated serial model to compare `from_location_id` against `serial->inventory_location_id`.

### `Rule::when()` for conditional required/prohibited

Using `Rule::when()` keeps all rules in one `rules()` method. The alternative — splitting into
multiple request classes — would create 3 nearly identical classes. One class with conditional
logic is cleaner for this use case.

### Policy's update() and delete() return false unconditionally

This is intentional. Even super-admin cannot update or delete movement rows. Code review must
enforce this contract.
