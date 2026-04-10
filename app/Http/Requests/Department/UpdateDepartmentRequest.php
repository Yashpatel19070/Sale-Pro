<?php

declare(strict_types=1);

namespace App\Http\Requests\Department;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('department'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper($this->code)]);
        }
    }

    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');
        $id         = $department->id;

        return [
            'name'        => ['required', 'string', 'max:100',
                              Rule::unique('departments', 'name')->ignore($id)->whereNull('deleted_at')],
            'code'        => ['required', 'string', 'max:20', 'regex:/^[A-Z]+$/',
                              Rule::unique('departments', 'code')->ignore($id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id'  => ['nullable', 'integer',
                              Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
