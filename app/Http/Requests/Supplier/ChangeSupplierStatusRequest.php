<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('supplier'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
