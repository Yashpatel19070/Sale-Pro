<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;

class SetDefaultCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setDefault', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        return [];
    }
}
