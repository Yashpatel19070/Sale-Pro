<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    protected function prepareForValidation(): void
    {
        // Strip tax-exemption fields unless the user is allowed to manage them —
        // existing values are preserved (update only touches provided keys).
        if (! $this->user()?->can('customers.manageTaxExemption')) {
            $this->replace($this->except(StoreCustomerRequest::TAX_EXEMPTION_FIELDS));
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($this->route('customer')),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::enum(CustomerStatus::class)],

            'tax_exempt' => ['nullable', 'boolean'],
            'tax_identification_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'entity_use_code' => ['nullable', 'string', 'regex:/^[A-Z]$/', Rule::in(StoreCustomerRequest::ENTITY_USE_CODES)],
            'exemption_certificate_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/', 'required_if:tax_exempt,1'],
            'exemption_signed_date' => ['nullable', 'date', 'required_if:tax_exempt,1', 'before_or_equal:today'],
            'exemption_expires_at' => ['nullable', 'date', 'required_if:tax_exempt,1', 'after:exemption_signed_date'],
            'exemption_exposure_zone' => ['nullable', 'string', 'max:60', 'required_if:tax_exempt,1'],
        ];
    }
}
