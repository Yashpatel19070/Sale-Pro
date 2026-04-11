# Customer Module — FormRequests & Policy

## StoreCustomerRequest

File: `app/Http/Requests/StoreCustomerRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CUSTOMERS_CREATE);
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            // status and source are required — form always pre-selects Lead / Other.
            // No null/empty allowed; service has no defaults for these.
            'status'        => ['required', Rule::enum(CustomerStatus::class)],
            'source'        => ['required', Rule::enum(CustomerSource::class)],
            'assigned_to'   => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postcode'      => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

## UpdateCustomerRequest

File: `app/Http/Requests/UpdateCustomerRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Customer $customer */
        $customer = $this->route('customer');

        return $this->user()->can('update', $customer);
    }

    public function rules(): array
    {
        /** @var \App\Models\Customer $customer */
        $customer = $this->route('customer');

        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone'         => ['nullable', 'string', 'max:30'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'job_title'     => ['nullable', 'string', 'max:100'],
            // status and source always pre-filled in the edit form — required, not nullable
            'status'        => ['required', Rule::enum(CustomerStatus::class)],
            'source'        => ['required', Rule::enum(CustomerSource::class)],
            'assigned_to'   => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postcode'      => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

## CustomerPolicy

File: `app/Policies/CustomerPolicy.php`

**Key rules:**
- `changeStatus` is a separate method — sales cannot change status even on assigned customers.
- Role priority in `view` and `update`: admin → manager (dept scope) → sales (assigned scope).
- `import` / `restore` / `delete` are admin-only (by permission matrix).

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CUSTOMERS_VIEW_ANY);
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_VIEW)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->department_id !== null
                && $user->department_id === $customer->department_id;
        }

        return $customer->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CUSTOMERS_CREATE);
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_EDIT)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->department_id !== null
                && $user->department_id === $customer->department_id;
        }

        return $customer->assigned_to === $user->id;
    }

    /**
     * Separate from update() — only admin and manager can change status.
     * Sales can edit assigned customers but CANNOT change their status.
     */
    public function changeStatus(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_EDIT)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function assign(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_ASSIGN);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_DELETE);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_RESTORE);
    }
}
```

## Permission Constants (add to `App\Enums\Permission`)

```php
// ── Customers ──────────────────────────────────────────────────────────────
const CUSTOMERS_VIEW_ANY = 'customers.view-any';
const CUSTOMERS_VIEW     = 'customers.view';
const CUSTOMERS_CREATE   = 'customers.create';
const CUSTOMERS_EDIT     = 'customers.edit';
const CUSTOMERS_DELETE   = 'customers.delete';
const CUSTOMERS_RESTORE  = 'customers.restore';
const CUSTOMERS_ASSIGN   = 'customers.assign';
```

> **Note**: `customers.import` is excluded. It will be added when the ImportExport
> module is built.
