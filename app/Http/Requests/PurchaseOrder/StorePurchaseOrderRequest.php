<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;

class StorePurchaseOrderRequest extends PurchaseOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }
}
