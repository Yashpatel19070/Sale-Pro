<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CalculateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],

            'shipping_address' => ['nullable', 'array'],
            'shipping_address.address_line1' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.city' => ['required_with:shipping_address', 'string', 'max:100'],
            'shipping_address.state' => ['required_with:shipping_address', 'string', 'max:10'],
            'shipping_address.postal_code' => ['required_with:shipping_address', 'string', 'max:20'],
            'shipping_address.country' => ['required_with:shipping_address', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.sku' => ['required', 'string', 'max:64'],
            'lines.*.fees' => ['nullable', 'array', 'max:10'],
            'lines.*.fees.*.name' => ['required_with:lines.*.fees', 'string', 'max:100'],
            'lines.*.fees.*.amount' => ['required_with:lines.*.fees', 'numeric', 'min:0'],
        ];
    }
}
