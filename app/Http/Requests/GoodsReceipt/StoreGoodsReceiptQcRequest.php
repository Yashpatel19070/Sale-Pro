<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceipt;

use App\Enums\Permission;
use App\Models\GoodsReceiptLine;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptQcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GOODS_RECEIPTS_UPDATE);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'integer', 'exists:goods_receipt_lines,id'],
            'lines.*.qty_passed' => ['required', 'integer', 'min:0'],
            'lines.*.qty_failed' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ($this->input('lines', []) as $i => $line) {
                    $grnLine = GoodsReceiptLine::find($line['goods_receipt_line_id'] ?? null);
                    if (! $grnLine) {
                        continue;
                    }
                    $sum = (int) ($line['qty_passed'] ?? 0) + (int) ($line['qty_failed'] ?? 0);
                    if ($sum !== (int) $grnLine->qty_received) {
                        $validator->errors()->add(
                            "lines.{$i}.qty_failed",
                            "Pass + fail ({$sum}) must equal received qty ({$grnLine->qty_received})."
                        );
                    }
                }
            },
        ];
    }
}
