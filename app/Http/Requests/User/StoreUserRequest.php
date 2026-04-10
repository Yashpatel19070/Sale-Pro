<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_id')],
            'department_id' => ['nullable', 'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
