<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use App\Enums\PipelineStage;
use App\Models\PoUnitJob;
use Illuminate\Foundation\Http\FormRequest;

class PassUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('unitJob');

        return $job instanceof PoUnitJob && $this->user()->can('pass', $job);
    }

    public function rules(): array
    {
        $job = $this->route('unitJob');
        $stage = $job?->current_stage;

        $rules = [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($stage === PipelineStage::SerialAssign) {
            $rules['serial_number'] = [
                'required',
                'string',
                'max:100',
                'unique:inventory_serials,serial_number',
                'unique:po_unit_jobs,pending_serial_number',
            ];
        }

        if ($stage === PipelineStage::Shelf) {
            $rules['inventory_location_id'] = ['required', 'integer', 'exists:inventory_locations,id'];
        }

        return $rules;
    }
}
