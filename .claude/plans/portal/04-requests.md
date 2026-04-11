# Customer Portal — Form Requests

Three FormRequests for portal actions. Stored in `Portal` sub-namespace.

---

## 1. RegisterCustomerRequest

**File:** `app/Http/Requests/Portal/RegisterCustomerRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email', 'unique:customers,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'phone'                 => ['required', 'string', 'max:20'],
            'company_name'          => ['nullable', 'string', 'max:255'],
            'address'               => ['required', 'string', 'max:255'],
            'city'                  => ['required', 'string', 'max:100'],
            'state'                 => ['required', 'string', 'max:100'],
            'postal_code'           => ['required', 'string', 'max:20'],
            'country'               => ['required', 'string', 'max:100'],
        ];
    }
}
```

### Notes
- `password` uses `confirmed` — requires a `password_confirmation` field in the form
- Email must be unique in **both** `users` and `customers` tables
- No `status` field — status is set to `active` automatically in the service

---

## 2. UpdatePortalProfileRequest

**File:** `app/Http/Requests/Portal/UpdatePortalProfileRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address'      => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'postal_code'  => ['required', 'string', 'max:20'],
            'country'      => ['required', 'string', 'max:100'],
        ];
    }
}
```

### Notes
- No `email` field — customer cannot change their own email from portal
- No `status` field — customer cannot change their own status
- Only profile fields the customer owns

---

## 3. ChangePortalPasswordRequest

**File:** `app/Http/Requests/Portal/ChangePortalPasswordRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class ChangePortalPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

### Notes
- `current_password` — raw string, verified against hash in the service (not using Laravel's `current_password` rule to keep logic in service)
- `password` uses `confirmed` — requires `password_confirmation` field in form
- Minimum 8 characters for new password

---

## Field Summary

| Field | Register | Update Profile | Change Password |
|-------|----------|---------------|-----------------|
| name | Required | Required | — |
| email | Required (unique) | — | — |
| password | Required (confirmed) | — | — |
| current_password | — | — | Required |
| new password | — | — | Required (confirmed) |
| phone | Required | Required | — |
| company_name | Optional | Optional | — |
| address | Required | Required | — |
| city | Required | Required | — |
| state | Required | Required | — |
| postal_code | Required | Required | — |
| country | Required | Required | — |
