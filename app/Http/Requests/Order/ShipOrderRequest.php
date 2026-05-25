<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ShipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ship', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:100'],
            'tracking' => ['required', 'string', 'max:100'],
            'label_cost' => ['required', 'numeric', 'min:0'],
            'shipped_at' => ['required', 'date'],
        ];
    }
}
