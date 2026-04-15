<?php

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
     * - transfer    → INVENTORY_MOVEMENTS_TRANSFER
     * - sale        → INVENTORY_MOVEMENTS_SELL
     * - adjustment  → INVENTORY_MOVEMENTS_ADJUST (admin/manager only)
     * - receive     → never permitted via UI
     */
    public function authorize(): bool
    {
        $type = $this->input('type');

        return match ($type) {
            MovementType::Transfer->value => $this->user()->can(Permission::INVENTORY_MOVEMENTS_TRANSFER),
            MovementType::Sale->value => $this->user()->can(Permission::INVENTORY_MOVEMENTS_SELL),
            MovementType::Adjustment->value => $this->user()->can(Permission::INVENTORY_MOVEMENTS_ADJUST),
            MovementType::Receive->value => true, // passes auth, blocked by Rule::notIn in rules()
            default => false, // completely unknown types blocked
        };
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'inventory_serial_id' => ['required', 'integer', 'exists:inventory_serials,id'],
            'type' => [
                'required',
                'string',
                Rule::notIn([MovementType::Receive->value]),
                Rule::in(array_column(MovementType::cases(), 'value')),
            ],

            // transfer — both locations required; prohibited for sale/adjustment
            'from_location_id' => [
                Rule::when(
                    in_array($type, [MovementType::Transfer->value, MovementType::Sale->value], true),
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

            // adjustment — status required; prohibited for other types
            'adjustment_status' => [
                Rule::when(
                    $type === MovementType::Adjustment->value,
                    ['required', 'string', Rule::in(['damaged', 'missing'])],
                    ['prohibited']
                ),
            ],

            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_serial_id.required' => 'Please select a serial number.',
            'inventory_serial_id.exists' => 'The selected serial number does not exist.',
            'type.required' => 'Please select a movement type.',
            'type.not_in' => 'Receive movements cannot be recorded manually.',
            'from_location_id.required' => 'A source location is required for this movement type.',
            'from_location_id.exists' => 'The selected source location does not exist.',
            'to_location_id.required' => 'A destination location is required for transfer.',
            'to_location_id.exists' => 'The selected destination location does not exist.',
            'adjustment_status.required' => 'Please select an adjustment status (damaged or missing).',
            'adjustment_status.in' => 'Adjustment status must be "damaged" or "missing".',
            'adjustment_status.prohibited' => 'Adjustment status is only used for adjustment type movements.',
            'reference.max' => 'Reference must be 150 characters or fewer.',
            'notes.max' => 'Notes must be 1000 characters or fewer.',
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

                if (! $serial) {
                    return;
                }

                if ($serial->status !== SerialStatus::InStock) {
                    $validator->errors()->add(
                        'inventory_serial_id',
                        "Serial {$serial->serial_number} is not in stock (current status: {$serial->status->value})."
                    );

                    return;
                }

                if (in_array($this->input('type'), [MovementType::Transfer->value, MovementType::Sale->value], true)) {
                    $fromId = (int) $this->input('from_location_id');
                    if ($fromId !== (int) $serial->inventory_location_id) {
                        $validator->errors()->add(
                            'from_location_id',
                            "Serial {$serial->serial_number} is not at that location."
                        );
                    }
                }
            },
        ];
    }
}
