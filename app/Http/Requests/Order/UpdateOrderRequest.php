<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Enums\OrderSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::enum(OrderSource::class)],
            'shipping_amount' => ['required', 'numeric', 'min:0'],

            'fees' => ['nullable', 'array'],
            'fees.*.name' => ['required_with:fees', 'string', 'max:100'],
            'fees.*.amount' => ['required_with:fees', 'numeric', 'min:0'],

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
