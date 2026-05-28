<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
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
        $customerId = $this->input('customer_id');

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'source' => ['required', Rule::enum(OrderSource::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'billing_address_id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $customerId)],
            'shipping_address_id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $customerId)],
            'shipping' => ['nullable', 'numeric', 'min:0'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_listing_id' => ['required', 'integer', Rule::exists('product_listings', 'id')->where('is_active', true)],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['required', 'numeric', 'min:0'],

            'lines.*.fees' => ['nullable', 'array'],
            'lines.*.fees.*.name' => ['required_with:lines.*.fees', 'string', 'max:100'],
            'lines.*.fees.*.amount' => ['required_with:lines.*.fees', 'numeric', 'min:0'],
            'lines.*.fees.*.tax_amount' => ['required_with:lines.*.fees', 'numeric', 'min:0'],
        ];
    }
}
