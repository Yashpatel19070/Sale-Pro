<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductListing;

use App\Enums\ListingVisibility;
use App\Models\ProductListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductListing::class);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'title' => ['required', 'string', 'max:200'],
            'visibility' => ['required', Rule::enum(ListingVisibility::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
