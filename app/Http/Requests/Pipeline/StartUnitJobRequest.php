<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class StartUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('start', $this->route('unitJob'));
    }

    public function rules(): array
    {
        return [];
    }
}
