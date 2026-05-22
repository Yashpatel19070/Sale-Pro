# Customer Portal — Form Requests

Three FormRequests for portal profile and password actions.

---

## 1. UpdatePortalProfileRequest

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
        ];
    }
}
```

### Notes

- No `email` field — customer cannot change their own email (admin-only)
- No `status` field — admin-only
- No address fields — addresses managed via the customer-addresses module

---

## 2. ChangePortalPasswordRequest

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

- `current_password` verified in service via `Hash::check()` — not using Laravel's `current_password` rule
- `password` uses `confirmed` — form must include `password_confirmation` input

---

## Field Summary

| Field | Update Profile | Change Password |
|-------|---------------|-----------------|
| name | Required | — |
| phone | Required | — |
| company_name | Optional | — |
| current_password | — | Required |
| password | — | Required (min:8, confirmed) |
