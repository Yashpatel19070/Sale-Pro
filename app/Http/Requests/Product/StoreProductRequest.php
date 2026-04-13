<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;

class StoreProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku', 'regex:/^[A-Za-z0-9\-\.]+$/'],
        ]);
    }
}
