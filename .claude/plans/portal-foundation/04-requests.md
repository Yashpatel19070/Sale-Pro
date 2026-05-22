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
            'email'                 => ['required', 'email', 'max:255', 'unique:customers,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'phone'                 => ['required', 'string', 'max:20'],
            'company_name'          => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

---

## Field Rules

| Field | Required | Rule | Notes |
|-------|----------|------|-------|
| name | Yes | string, max:255 | |
| email | Yes | email, unique:customers | Unique in customers table only — no users link |
| password | Yes | min:8, confirmed | Requires `password_confirmation` field |
| phone | Yes | string, max:20 | |
| company_name | No | string, max:255 | nullable |

---

## Notes

- Email unique check is **customers table only** — no `users` table involved
- No address fields — addresses managed via the customer-addresses module after registration
- `authorize()` returns `true` — no policy needed for self-registration
- `password` uses `confirmed` — form must include `password_confirmation` input
- No `status` field — always set to `active` in service
