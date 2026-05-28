<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerAddressFromOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', Order::class)) {
            return false;
        }

        $customer = Customer::find($this->input('customer_id'));
        if ($customer === null) {
            return true; // let validation `exists` rule produce 422
        }

        return $this->user()->can('create', [CustomerAddress::class, $customer]);
    }

    public function rules(): array
    {
        $customerId = (int) $this->input('customer_id');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'label' => [
                'required', 'string', 'max:50',
                Rule::unique('customer_addresses', 'label')
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:10'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
        ];
    }
}
