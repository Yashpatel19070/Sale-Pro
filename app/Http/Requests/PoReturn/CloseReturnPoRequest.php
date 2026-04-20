<?php

declare(strict_types=1);

namespace App\Http\Requests\PoReturn;

use Illuminate\Foundation\Http\FormRequest;

class CloseReturnPoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [];
    }
}
