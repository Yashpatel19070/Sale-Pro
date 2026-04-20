# Supplier Module — FormRequests

Three request classes. All delegate authorization to Policy.

---

## 1. StoreSupplierRequest

**File:** `app/Http/Requests/StoreSupplierRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.create');
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:suppliers,email'],
            'phone'         => ['required', 'string', 'max:20'],
            'address'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
            'status'        => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
```

---

## 2. UpdateSupplierRequest

**File:** `app/Http/Requests/UpdateSupplierRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.update');
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name'          => ['required', 'string', 'max:255'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'email'         => [
                'required',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplierId),
            ],
            'phone'         => ['required', 'string', 'max:20'],
            'address'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
            'status'        => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
```

---

## 3. ChangeSupplierStatusRequest

**File:** `app/Http/Requests/ChangeSupplierStatusRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.changeStatus');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
```

---

## Rules Summary

| Request | authorize() | Key Validation |
|---------|-------------|----------------|
| `StoreSupplierRequest` | `can('suppliers.create')` | email unique globally |
| `UpdateSupplierRequest` | `can('suppliers.update')` | email unique ignoring self |
| `ChangeSupplierStatusRequest` | `can('suppliers.changeStatus')` | status enum only |

## Notes
- `authorize()` delegates to permission check — Policy catches the same check in controller too
- `$this->route('supplier')` returns route-bound `Supplier` model — use `?->id` to safely get ID
- `Rule::enum(SupplierStatus::class)` rejects any value not in the enum (e.g. 'blocked', 'pending')
- All nullable fields must explicitly list `'nullable'` — never omit and assume optional
