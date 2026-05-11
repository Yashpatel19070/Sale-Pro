<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceipt;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrnSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'integer', 'exists:goods_receipt_lines,id'],
            'lines.*.inventory_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'lines.*.purchase_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
