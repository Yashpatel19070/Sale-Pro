<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sku' => ['required', 'string', 'max:64',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
                'regex:/^[A-Za-z0-9\-\.]+$/'],
        ]);
    }
}
