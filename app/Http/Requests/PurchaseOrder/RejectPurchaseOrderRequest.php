<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class RejectPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::PURCHASE_ORDERS_REJECT);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
