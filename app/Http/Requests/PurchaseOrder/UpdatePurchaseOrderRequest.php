<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

class UpdatePurchaseOrderRequest extends PurchaseOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchaseOrder'));
    }
}
