# Portal Foundation — Form Requests

One FormRequest for registration. Login/logout use inline `$request->validate()`.

---

## RegisterRequest

**File:** `app/Http/Requests/Portal/Auth/RegisterRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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

---

## Field Rules

| Field | Required | Rule | Notes |
|-------|----------|------|-------|
| name | Yes | string, max:255 | |
| email | Yes | email, unique:users + unique:customers | Cannot already exist in either table |
| password | Yes | min:8, confirmed | Requires `password_confirmation` field |
| phone | Yes | string, max:20 | |
| company_name | No | string, max:255 | nullable |
| address | Yes | string, max:255 | |
| city | Yes | string, max:100 | |
| state | Yes | string, max:100 | |
| postal_code | Yes | string, max:20 | |
| country | Yes | string, max:100 | |

---

## Notes
- Email must be unique in **both** `users` and `customers` tables
- `password` uses `confirmed` — form must include `password_confirmation` input
- No `status` field — always set to `active` in service
- `authorize()` returns `true` — no policy check needed for self-registration
