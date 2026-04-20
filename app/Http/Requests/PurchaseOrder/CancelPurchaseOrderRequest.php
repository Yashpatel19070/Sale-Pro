<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [
            'cancel_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
