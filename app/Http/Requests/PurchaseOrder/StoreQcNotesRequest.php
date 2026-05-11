<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreQcNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qc_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
