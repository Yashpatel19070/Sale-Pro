<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class DeliverOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deliver', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'delivered_at' => ['required', 'date'],
        ];
    }
}
