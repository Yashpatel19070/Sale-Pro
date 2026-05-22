# Customer Module — Form Requests

Three FormRequest classes handle all input validation.

---

## 1. StoreCustomerRequest

**File:** `app/Http/Requests/StoreCustomerRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone'        => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'status'       => ['required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
```

---

## 2. UpdateCustomerRequest

**File:** `app/Http/Requests/UpdateCustomerRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($this->route('customer')),
            ],
            'phone'        => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'status'       => ['required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
```

### Key difference from StoreRequest
- `email` uses `Rule::unique()->ignore($this->route('customer'))` to allow updating without email conflict on the same record
- `$this->route('customer')` is the route-bound model — retrieved explicitly via `$this->route()` for correctness

---

## 3. ChangeCustomerStatusRequest

**File:** `app/Http/Requests/ChangeCustomerStatusRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
```

---

## Field Rules Summary

| Field | Required | Type | Max | Notes |
|-------|----------|------|-----|-------|
| name | Yes | string | 255 | |
| email | Yes | email | 255 | unique in customers table |
| phone | Yes | string | 20 | |
| company_name | No | string | 255 | nullable |
| status | Yes | enum | — | must be a valid CustomerStatus value |

---

## Notes
- `authorize()` delegates to `CustomerPolicy` via `$this->user()->can()` — controller also calls `$this->authorize()` (defense in depth)
- `Rule::enum(CustomerStatus::class)` validates that status is one of: `active`, `inactive`, `blocked`
- `$this->route('customer')` in `UpdateCustomerRequest` retrieves the route-bound `Customer` model explicitly
