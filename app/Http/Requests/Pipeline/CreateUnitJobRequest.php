<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PoUnitJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createJob', PoUnitJob::class);
    }

    public function rules(): array
    {
        return [
            'po_line_id' => [
                'required',
                'integer',
                Rule::exists('po_lines', 'id')->where(function ($query): void {
                    $query->whereIn('purchase_order_id', function ($sub): void {
                        $sub->select('id')
                            ->from('purchase_orders')
                            ->where('type', PoType::Purchase->value)
                            ->whereIn('status', [PoStatus::Open->value, PoStatus::Partial->value]);
                    });
                }),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
