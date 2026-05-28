<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public const ENTITY_USE_CODES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

    /** Tax-exemption fields gated behind the customers.manageTaxExemption permission. */
    public const TAX_EXEMPTION_FIELDS = [
        'tax_exempt',
        'tax_identification_number',
        'entity_use_code',
        'exemption_certificate_number',
        'exemption_signed_date',
        'exemption_expires_at',
        'exemption_exposure_zone',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        // Strip tax-exemption fields unless the user is allowed to manage them —
        // prevents a staff user from self-granting tax exemption (security #6).
        if (! $this->user()?->can('customers.manageTaxExemption')) {
            $this->replace($this->except(self::TAX_EXEMPTION_FIELDS));
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::enum(CustomerStatus::class)],

            'tax_exempt' => ['nullable', 'boolean'],
            'tax_identification_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'entity_use_code' => ['nullable', 'string', 'regex:/^[A-Z]$/', Rule::in(self::ENTITY_USE_CODES)],
            'exemption_certificate_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/', 'required_if:tax_exempt,1'],
            'exemption_signed_date' => ['nullable', 'date', 'required_if:tax_exempt,1', 'before_or_equal:today'],
            'exemption_expires_at' => ['nullable', 'date', 'required_if:tax_exempt,1', 'after:exemption_signed_date'],
            'exemption_exposure_zone' => ['nullable', 'string', 'max:60', 'required_if:tax_exempt,1'],
        ];
    }
}
