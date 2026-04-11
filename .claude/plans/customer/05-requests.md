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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone'        => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address'      => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'postal_code'  => ['required', 'string', 'max:20'],
            'country'      => ['required', 'string', 'max:100'],
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
        return true; // Authorization handled in controller via Policy
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($this->customer),
            ],
            'phone'        => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address'      => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'postal_code'  => ['required', 'string', 'max:20'],
            'country'      => ['required', 'string', 'max:100'],
            'status'       => ['required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
```

### Key difference from StoreRequest
- `email` uses `Rule::unique()->ignore($this->customer)` to allow updating without email conflict on the same record
- `$this->customer` is the route-bound model — available automatically via Laravel

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
        return true; // Authorization handled in controller via Policy
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
| address | Yes | string | 255 | |
| city | Yes | string | 100 | |
| state | Yes | string | 100 | |
| postal_code | Yes | string | 20 | |
| country | Yes | string | 100 | |
| status | Yes | enum | — | must be a valid CustomerStatus value |

---

## Notes
- `authorize()` always returns `true` — policy checks happen in the controller
- `Rule::enum(CustomerStatus::class)` validates that status is one of: `active`, `inactive`, `blocked`
- `$this->customer` in `UpdateCustomerRequest` refers to the route-bound `Customer` model automatically
