<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        return [];
    }
}
