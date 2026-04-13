<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

abstract class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('sku')) {
            $this->merge(['sku' => strtoupper($this->input('sku'))]);
        }
    }

    protected function sharedRules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'lt:regular_price'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU may only contain letters, numbers, hyphens, and dots.',
            'sale_price.lt' => 'Sale price must be less than the regular price.',
        ];
    }
}
