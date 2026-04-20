<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PurchaseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'skip_tech' => ['boolean'],
            'skip_qa' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty_ordered' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'skip_tech' => $this->boolean('skip_tech'),
            'skip_qa' => $this->boolean('skip_qa'),
        ]);
    }
}
