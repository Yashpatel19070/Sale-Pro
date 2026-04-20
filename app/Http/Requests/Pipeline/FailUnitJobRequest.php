<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class FailUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fail', $this->route('unitJob'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
