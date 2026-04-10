<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $id = $user->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_id')->ignore($id)],
            'department_id' => ['nullable', 'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
