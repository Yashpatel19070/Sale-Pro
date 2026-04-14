<?php

declare(strict_types=1);

namespace App\Http\Requests\InventorySerial;

use App\Models\InventorySerial;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventorySerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InventorySerial $serial */
        $serial = $this->route('inventorySerial');

        return $this->user()->can('update', $serial);
    }

    public function rules(): array
    {
        // IMPORTANT: serial_number and purchase_price are intentionally absent.
        // They are immutable after creation and must never appear here.
        return [
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
