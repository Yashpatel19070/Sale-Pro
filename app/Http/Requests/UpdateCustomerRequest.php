<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Customer $customer */
        $customer = $this->route('customer');

        return $this->user()->can('update', $customer);
    }

    public function rules(): array
    {
        /** @var \App\Models\Customer $customer */
        $customer = $this->route('customer');

        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone'         => ['nullable', 'string', 'max:30'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            // status and source always pre-filled in the edit form — required, not nullable
            'status'        => ['required', Rule::enum(CustomerStatus::class)],
            'source'        => ['required', Rule::enum(CustomerSource::class)],
            'assigned_to'   => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postcode'      => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
