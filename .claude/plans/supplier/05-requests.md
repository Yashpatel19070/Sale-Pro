# Supplier Module — FormRequests

Three request classes in `app/Http/Requests/Supplier/` subfolder.
`authorize()` delegates to Policy via `can('action', Model)` — never raw permission strings.

---

## 1. StoreSupplierRequest

**File:** `app/Http/Requests/Supplier/StoreSupplierRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Supplier::class);
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'email'         => ['required', 'email:rfc', 'max:255', 'unique:suppliers,email'],
            'phone'         => ['required', 'string', 'max:20'],
            'address'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:10000'],
            'status'        => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
```

---

## 2. UpdateSupplierRequest

**File:** `app/Http/Requests/Supplier/UpdateSupplierRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supplier'));
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'email'         => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($this->route('supplier')),
            ],
            'phone'         => ['required', 'string', 'max:20'],
            'address'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:10000'],
            'status'        => ['required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
```

---

## 3. ChangeSupplierStatusRequest

**File:** `app/Http/Requests/Supplier/ChangeSupplierStatusRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

## Security Notes

| Field | Threat mitigated |
|-------|-----------------|
| `email:rfc` | Rejects malformed emails that pass basic `email` rule |
| `max:255` on all string fields | Prevents DB column overflow (VARCHAR 255) |
| `max:10000` on `notes` | Prevents unbounded text abuse on TEXT column |
| `max:20` on `phone` | Prevents overflow on VARCHAR(20) column |
| `Rule::enum(SupplierStatus::class)` | Whitelist-only — rejects any unlisted status value |
| `unique:suppliers,email` | DB-level uniqueness enforced at validation layer, not just DB |
| `Rule::unique()->ignore($this->route('supplier'))` | Passes model to Laravel — extracts PK internally, PHPStan-safe |
| `authorize()` delegates to Policy | Policy fires correctly via `Gate::inspect()` — not bypassed |

## authorize() Pattern Rules
- `StoreSupplierRequest` → `can('create', Supplier::class)` — no model instance needed for create
- `UpdateSupplierRequest` → `can('update', $this->route('supplier'))` — passes bound model to Policy
- `ChangeSupplierStatusRequest` → `return true` — controller's `$this->authorize('changeStatus', $supplier)` handles it; matches real `ChangeCustomerStatusRequest` pattern

## Notes
- All nullable fields must explicitly declare `'nullable'` — never omit
- `ignore()` receives the model object directly — Laravel calls `->getKey()` internally
- Namespace is `App\Http\Requests\Supplier\` — update controller imports accordingly
