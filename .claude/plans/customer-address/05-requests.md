# Customer Address Module — Form Requests

Four FormRequest classes — one per write action.

---

## 1. StoreCustomerAddressRequest

**File:** `app/Http/Requests/CustomerAddress/StoreCustomerAddressRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [CustomerAddress::class, $this->route('customer')]);
    }

    public function rules(): array
    {
        return [
            'label'         => ['required', 'string', 'max:50'],
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:100'],
            'state'         => ['required', 'string', 'max:10'],
            'postal_code'   => ['required', 'string', 'max:20'],
            'country'       => ['required', 'string', 'size:2'],
        ];
    }
}
```

---

## 2. UpdateCustomerAddressRequest

**File:** `app/Http/Requests/CustomerAddress/UpdateCustomerAddressRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        return [
            'label'         => ['required', 'string', 'max:50'],
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:100'],
            'state'         => ['required', 'string', 'max:10'],
            'postal_code'   => ['required', 'string', 'max:20'],
            'country'       => ['required', 'string', 'size:2'],
        ];
    }
}
```

---

## 3. DeleteCustomerAddressRequest

**File:** `app/Http/Requests/CustomerAddress/DeleteCustomerAddressRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

## 4. SetDefaultCustomerAddressRequest

**File:** `app/Http/Requests/CustomerAddress/SetDefaultCustomerAddressRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerAddress;

use Illuminate\Foundation\Http\FormRequest;

class SetDefaultCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setDefault', [$this->route('address'), $this->route('customer')]);
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

## Field Rules Reference

| Field | Required | Type | Constraint | Notes |
|-------|----------|------|------------|-------|
| `label` | Yes | string | max:50 | e.g. `Home`, `Work`, `Billing` |
| `first_name` | Yes | string | max:100 | Recipient name |
| `last_name` | Yes | string | max:100 | Recipient name |
| `email` | No | email | max:255 | nullable |
| `phone` | No | string | max:30 | nullable |
| `address_line1` | Yes | string | max:255 | Street address |
| `address_line2` | No | string | max:255 | nullable — apt, suite, unit |
| `city` | Yes | string | max:100 | |
| `state` | Yes | string | max:10 | e.g. `TX`, `AZ` |
| `postal_code` | Yes | string | max:20 | |
| `country` | Yes | string | size:2 | Exactly 2 chars — ISO 3166-1 alpha-2 |

---

## Notes

- `is_default` is NOT in store/update rules — set only via dedicated `setDefault` PATCH route
- `country` uses `size:2` — must be exactly 2 characters (`max:2` would allow 1-character values)
- Store and Update rules are identical — no uniqueness constraints needed
- `authorize()` delegates to policy via `$this->user()->can()` — passes route-bound models as context
- Controller also calls `$this->authorize()` — both checks are independent; both must pass (defense in depth)
- `DeleteCustomerAddressRequest` and `SetDefaultCustomerAddressRequest` have empty `rules()` — no body sent, authorization only
