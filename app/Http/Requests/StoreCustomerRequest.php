<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CUSTOMERS_CREATE);
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            // status and source are required — form always pre-selects Lead / Other.
            // No null/empty allowed; service has no defaults for these.
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
