<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;
        $addressId = $this->route('address')->id;

        return [
            'label' => [
                'required', 'string', 'max:50',
                Rule::unique('customer_addresses', 'label')
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at')
                    ->ignore($addressId),
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
            'country' => ['required', 'string', 'size:2'],
        ];
    }
}
