<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Enums\OrderSource;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => (int) $this->customer_id,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'source' => ['required', 'string', Rule::enum(OrderSource::class)],
            'shipping_amount' => ['required', 'numeric', 'min:0'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.serial_id' => ['required', 'integer', 'exists:inventory_serials,id', 'distinct'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],

            'fees' => ['nullable', 'array'],
            'fees.*.name' => ['required_with:fees', 'string', 'max:100'],
            'fees.*.amount' => ['required_with:fees', 'numeric', 'min:0'],

            // Billing address (optional — cash sales may skip billing)
            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing.address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'billing.first_name' => ['nullable', 'string', 'max:100'],
            'billing.last_name' => ['nullable', 'string', 'max:100'],
            'billing.email' => ['nullable', 'email', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:30'],
            'billing.line1' => ['nullable', 'string', 'max:255'],
            'billing.line2' => ['nullable', 'string', 'max:255'],
            'billing.city' => ['nullable', 'string', 'max:100'],
            'billing.state' => ['nullable', 'string', 'max:10'],
            'billing.postal_code' => ['nullable', 'string', 'max:20'],
            'billing.country' => ['nullable', 'string', 'size:2'],

            // Shipping address (optional — digital/pickup orders may skip)
            'shipping.address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'shipping.first_name' => ['nullable', 'string', 'max:100'],
            'shipping.last_name' => ['nullable', 'string', 'max:100'],
            'shipping.email' => ['nullable', 'email', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:30'],
            'shipping.line1' => ['nullable', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => ['nullable', 'string', 'max:100'],
            'shipping.state' => ['nullable', 'string', 'max:10'],
            'shipping.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping.country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
