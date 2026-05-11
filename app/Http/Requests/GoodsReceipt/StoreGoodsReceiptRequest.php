<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceipt;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GOODS_RECEIPTS_CREATE);
    }

    public function prepareForValidation(): void
    {
        if (is_array($this->input('lines'))) {
            $filtered = array_values(
                array_filter(
                    $this->input('lines'),
                    fn ($l) => isset($l['qty_received']) && (float) $l['qty_received'] > 0
                )
            );
            $this->merge(['lines' => $filtered]);
        }
    }

    public function rules(): array
    {
        return [
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.qty_received' => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
