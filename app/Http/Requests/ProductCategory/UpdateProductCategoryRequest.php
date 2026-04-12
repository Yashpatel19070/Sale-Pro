<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductCategory;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product_category'));
    }

    public function rules(): array
    {
        /** @var ProductCategory $category */
        $category = $this->route('product_category');

        $forbiddenIds = array_merge(
            [$category->id],
            $category->load('children.children.children')->descendantIds()
        );

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->whereNull('deleted_at'),
                Rule::notIn($forbiddenIds),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($category->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
