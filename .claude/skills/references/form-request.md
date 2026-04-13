# FormRequest Reference

## Rules — What a FormRequest Does and Doesn't Do

| ✅ FormRequest owns | ❌ Never in a FormRequest |
|--------------------|--------------------------|
| Input validation | Business logic |
| Authorization (`can()` check) | DB writes |
| Custom error messages | Service calls |
| Cross-field validation (`after()`) | Redirects |

**One-liner:** validate input and check permission. Nothing else.

---

## Anatomy of a FormRequest

```php
<?php

namespace App\Http\Requests\Order;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    // 1. Authorization — always uses Permission constants
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ORDERS_CREATE);
    }

    // 2. Validation rules
    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_address'   => ['required', 'string', 'max:500'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'coupon_code'        => ['nullable', 'string', 'exists:coupons,code'],
        ];
    }

    // 3. Custom error messages — override Laravel defaults
    public function messages(): array
    {
        return [
            'items.required'            => 'Your cart is empty.',
            'items.min'                 => 'Your cart is empty.',
            'items.*.product_id.exists' => 'One or more products no longer exist.',
            'items.*.quantity.min'      => 'Quantity must be at least 1.',
            'shipping_address.required' => 'Please provide a shipping address.',
        ];
    }

    // 4. Cross-field / complex validation rules() can't handle
    public function after(): array
    {
        return [
            function ($validator) {
                foreach ($this->input('items', []) as $index => $item) {
                    $product = \App\Models\Product::find($item['product_id'] ?? null);
                    if ($product && $product->stock < $item['quantity']) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Only {$product->stock} units of '{$product->name}' available."
                        );
                    }
                }
            },
        ];
    }
}
```

---

## Authorization — Always Permission Constants

```php
// ✅ Always use Permission constants — DB-driven via Spatie
public function authorize(): bool
{
    return $this->user()->can(Permission::ORDERS_CREATE);
}

// ✅ Update requests — pass the bound model, not just a permission string
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('order'));
    // Passes the model to the Policy — Policy's update() method fires
}

// ❌ Never return true — it bypasses the Policy entirely
// The controller's $this->authorize() is a SEPARATE gate check.
// FormRequest authorize() should ALSO enforce permissions.
public function authorize(): bool
{
    return true; // WRONG — everyone can submit this form
}

// ❌ Never hardcode permission strings
public function authorize(): bool
{
    return $this->user()->can('orders.create'); // typo-prone
}

// ❌ Never check roles in FormRequest
public function authorize(): bool
{
    return $this->user()->hasRole('admin'); // wrong layer, wrong approach
}
```

> **Critical:** `$this->authorize()` in the controller and `authorize()` in the FormRequest are
> independent checks. Both must be correct. `return true` in FormRequest means anyone who can
> reach the route can submit the form — the controller gate runs separately but too late if
> the FormRequest already passed.

---

## prepareForValidation() — Normalise Input Before Rules Run

Use `prepareForValidation()` when a field is always stored in a canonical form (uppercase, slug, trimmed).
**Why it matters for unique rules:** if the DB stores `ABC-001` but the user submits `abc-001`, the
`unique` rule sees them as different strings and passes a duplicate through. Normalise first.

```php
abstract class ProductRequest extends FormRequest
{
    // Runs before rules() — merges normalised value so unique rule checks correct case
    protected function prepareForValidation(): void
    {
        if ($this->filled('sku')) {
            $this->merge(['sku' => strtoupper($this->input('sku'))]);
        }
    }
}
```

**Pattern: base FormRequest class for shared normalisation + shared rules**
```php
// Base class — normalisation + shared rules + messages
abstract class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void { /* normalise */ }
    protected function sharedRules(): array { /* common fields */ }
    public function messages(): array { /* custom messages */ }
}

// Subclass — own authorize() + merge SKU rule
class StoreProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sku' => ['required', 'unique:products,sku', ...],
        ]);
    }
}

class UpdateProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sku' => ['required', Rule::unique('products', 'sku')->ignore($this->route('product')), ...],
        ]);
    }
}
```

---

## Update Request — Unique Rule Ignoring Current Record

```php
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::USERS_EDIT);
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            // Ignore current record when checking email uniqueness
            // ✅ Use $this->route('user') — not $this->user (magic property, PHPStan fails)
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
```

---

## Validation Rules — Full Reference

### Presence
```php
'field' => ['required']
'field' => ['nullable']                        // allow null, skip rules if null
'field' => ['sometimes']                       // only validate if present in input
'field' => ['required_if:other_field,value']   // required when other = value
'field' => ['required_unless:other,value']     // required unless other = value
'field' => ['required_with:other']             // required when other present
'field' => ['required_without:other']          // required when other absent
'field' => ['required_with_all:a,b']           // required when both a and b present
'field' => ['prohibited_if:other,value']       // must be absent when other = value
```

### Strings
```php
'field' => ['string', 'min:2', 'max:255']
'field' => ['email', 'max:255']
'field' => ['url']
'field' => ['uuid']
'field' => ['alpha']                           // letters only
'field' => ['alpha_num']                       // letters + numbers
'field' => ['alpha_dash']                      // letters, numbers, dashes, underscores
'field' => ['starts_with:foo,bar']
'field' => ['ends_with:foo,bar']
'field' => ['regex:/^[A-Z0-9]+$/']
'field' => ['not_regex:/[<>]/']
```

### Numbers
```php
'field' => ['integer', 'min:1', 'max:999']
'field' => ['numeric', 'between:0,100']
'field' => ['decimal:0,2']                     // up to 2 decimal places
'field' => ['digits:4']                        // exactly 4 digits
'field' => ['digits_between:4,6']
'field' => ['gt:other_field']                  // greater than another field
'field' => ['gte:other_field']                 // greater than or equal
'field' => ['lt:other_field']
'field' => ['lte:other_field']
```

### Uniqueness & Existence
```php
// Basic unique
'email'  => ['unique:users,email']

// Unique ignoring current record (update)
'email'  => [Rule::unique('users', 'email')->ignore($this->route('user')->id)]

// Unique with extra where clause
'slug'   => [Rule::unique('posts', 'slug')->where('user_id', $this->user()->id)]

// Exists in DB
'category_id' => ['exists:categories,id']

// Exists with extra condition
'product_id'  => [Rule::exists('products', 'id')->where('is_active', true)]
```

### Enums
```php
// Validate against PHP Enum values
'status' => [Rule::enum(OrderStatus::class)]

// Or manually
'status' => [Rule::in(array_column(OrderStatus::cases(), 'value'))]
```

### Arrays
```php
'tags'        => ['array', 'min:1', 'max:10']
'tags.*'      => ['string', 'max:50', 'distinct']  // distinct = no duplicates
'items'       => ['array', 'min:1']
'items.*.id'  => ['required', 'integer', 'exists:products,id']
'ids'         => ['array']
'ids.*'       => ['integer', 'exists:users,id']
```

### Files & Images
```php
'avatar'   => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048']  // max 2MB
'document' => ['file', 'mimes:pdf,doc,docx', 'max:10240']       // max 10MB
'photo'    => ['image', 'dimensions:min_width=100,min_height=100']
```

### Dates
```php
'starts_at' => ['required', 'date', 'after:today']
'ends_at'   => ['required', 'date', 'after:starts_at']
'birthday'  => ['required', 'date', 'before:today']
'date'      => ['required', 'date_format:Y-m-d']
```

### Booleans
```php
'is_active'   => ['boolean']          // true, false, 1, 0, "1", "0"
'agree_terms' => ['accepted']         // must be true / "on" / "yes" / 1
```

---

## Custom Rule Class

Use when inline rules aren't expressive enough or the logic is reusable.

```bash
php artisan make:rule ValidPostalCode
```

```php
// app/Rules/ValidPostalCode.php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPostalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\d{5}(-\d{4})?$/', $value)) {
            $fail('The :attribute must be a valid US postal code.');
        }
    }
}

// Use in rules()
'postal_code' => ['required', 'string', new ValidPostalCode]
```

---

## Folder Structure

```
app/Http/Requests/
├── Order/
│   ├── CreateOrderRequest.php
│   ├── UpdateOrderRequest.php
│   └── CancelOrderRequest.php
├── Admin/
│   └── User/
│       ├── CreateUserRequest.php
│       └── UpdateUserRequest.php
└── Profile/
    └── UpdateProfileRequest.php
```

**One FormRequest per action.** Never reuse a create request for update — they have different rules (unique ignore, nullable fields on partial update, different permissions).

---

## Quick Reference

```
authorize() → $this->user()->can(Permission::CONSTANT)
rules()     → declare every field, every constraint
messages()  → override confusing default messages
after()     → cross-field, DB-based, or complex validation

Always:
- One FormRequest per action (create ≠ update)
- Use Permission constants in authorize()
- Use Rule::unique()->ignore() on update
- Use Rule::enum() for enum fields
- Use after() for stock checks, date range checks, etc
- Pass $request->validated() to service — never $request->all()
```
