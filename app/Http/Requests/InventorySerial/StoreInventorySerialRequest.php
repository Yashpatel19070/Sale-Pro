<?php

declare(strict_types=1);

namespace App\Http\Requests\InventorySerial;

use App\Models\InventorySerial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'inventory_location_id' => [
                'required',
                'integer',
                Rule::exists('inventory_locations', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('inventory_serials', 'serial_number')->withoutTrashed()],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.unique' => 'This serial number already exists in the system.',
            'received_at.before_or_equal' => 'Received date cannot be in the future.',
            'inventory_location_id.exists' => 'The selected shelf location does not exist or is no longer active.',
        ];
    }
}
