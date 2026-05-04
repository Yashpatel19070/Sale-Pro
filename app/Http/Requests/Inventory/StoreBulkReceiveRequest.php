<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:500'],
            'inventory_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'source_ref' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.min' => 'Quantity must be at least 1.',
            'qty.max' => 'Maximum 500 units per bulk receive.',
        ];
    }
}
