<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductListing;

use App\Enums\ListingVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product_listing'));
    }

    public function rules(): array
    {
        // product_id intentionally absent — immutable after creation
        return [
            'title' => ['required', 'string', 'max:200'],
            'visibility' => ['required', Rule::enum(ListingVisibility::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
