# User Module — Form Requests & Policy

## StoreUserRequest

File: `app/Http/Requests/User/StoreUserRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')],
            'password'      => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone'         => ['nullable', 'string', 'max:30'],
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            'employee_id'   => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status'        => ['required', Rule::enum(UserStatus::class)],
            'hired_at'      => ['nullable', 'date', 'before_or_equal:today'],
            'timezone'      => ['required', 'string', 'timezone:all'],
            'role'          => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
```

## UpdateUserRequest

File: `app/Http/Requests/User/UpdateUserRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
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
        $userId = $this->route('user')->id;

        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email:rfc,dns', 'max:255',
                               Rule::unique('users', 'email')->ignore($userId)],
            'phone'         => ['nullable', 'string', 'max:30'],
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            'employee_id'   => ['nullable', 'string', 'max:50',
                               Rule::unique('users', 'employee_id')->ignore($userId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status'        => ['required', Rule::enum(UserStatus::class)],
            'hired_at'      => ['nullable', 'date', 'before_or_equal:today'],
            'timezone'      => ['required', 'string', 'timezone:all'],
            'role'          => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
```

## UpdateProfileRequest (self-service)

File: `app/Http/Requests/ProfileUpdateRequest.php` (update existing Breeze file)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc,dns', 'max:255',
                          Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone'    => ['nullable', 'string', 'max:30'],
            'avatar'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }
}
```

## UserPolicy

File: `app/Policies/UserPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $authUser, User $user): bool
    {
        if ($authUser->hasRole('admin')) {
            return true;
        }

        if ($authUser->hasRole('manager')) {
            // Manager can view users in their own department
            return $authUser->department_id !== null
                && $authUser->department_id === $user->department_id;
        }

        // Sales can only view own profile
        return $authUser->id === $user->id;
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function update(User $authUser, User $user): bool
    {
        if ($authUser->hasRole('admin')) {
            return true;
        }

        // Any authenticated user can edit their own basic profile
        return $authUser->id === $user->id;
    }

    public function delete(User $authUser, User $user): bool
    {
        // Cannot delete yourself
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->hasRole('admin');
    }

    public function restore(User $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function changeStatus(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin') && $authUser->id !== $user->id;
    }

    public function resetPassword(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin');
    }
}
```

## Policy Registration

In `AppServiceProvider::boot()`:

```php
Gate::policy(User::class, UserPolicy::class);
```
