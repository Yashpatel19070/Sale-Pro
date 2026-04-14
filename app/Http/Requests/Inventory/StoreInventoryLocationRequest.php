<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\InventoryLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryLocation::class);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('inventory_locations', 'code')->withoutTrashed(),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The code may only contain letters, numbers, hyphens, and underscores.',
            'code.unique' => 'This location code is already in use.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
