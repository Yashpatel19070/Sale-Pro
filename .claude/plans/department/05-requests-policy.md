# Department Module — Form Requests & Policy

## StoreDepartmentRequest

File: `app/Http/Requests/Department/StoreDepartmentRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Department::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('departments', 'name')],
            'code'        => ['required', 'string', 'max:20', 'alpha', 'uppercase',
                              Rule::unique('departments', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id'  => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active'   => ['boolean'],
        ];
    }
}
```

## UpdateDepartmentRequest

File: `app/Http/Requests/Department/UpdateDepartmentRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('department'));
    }

    public function rules(): array
    {
        $id = $this->route('department')->id;

        return [
            'name'        => ['required', 'string', 'max:100',
                              Rule::unique('departments', 'name')->ignore($id)],
            'code'        => ['required', 'string', 'max:20', 'alpha', 'uppercase',
                              Rule::unique('departments', 'code')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id'  => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active'   => ['boolean'],
        ];
    }
}
```

## DepartmentPolicy

File: `app/Policies/DepartmentPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
```

## Policy Registration

In `app/Providers/AppServiceProvider.php` (or `AuthServiceProvider` if present):

```php
use App\Models\Department;
use App\Policies\DepartmentPolicy;

Gate::policy(Department::class, DepartmentPolicy::class);
```

## Validation Notes

- `code` uses `'alpha'` + `'uppercase'` rules (Laravel 12 `uppercase` rule).
  If not available, use a `Rule::in()` list or custom rule.
- `manager_id` must exist in `users` table — the controller view query
  restricts the dropdown to active users, but the request still validates
  against the full table to prevent race conditions.
- `is_active` is a checkbox; cast to boolean before saving. The form
  should submit `1`/`0` or use `@checked` directive.
