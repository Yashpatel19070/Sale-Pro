# Customer Module — Controller

**File:** `app/Http/Controllers/CustomerController.php`

---

## Full Controller Code

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Http\Requests\ChangeCustomerStatusRequest;
use App\Http\Requests\Customer\SetCustomerPasswordRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->service->paginate(
            $request->only(['search', 'status'])
        );

        return view('customers.index', [
            'customers' => $customers,
            'statuses'  => CustomerStatus::cases(),
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'statuses' => CustomerStatus::cases(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $this->service->store($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $defaultAddress = $customer->addresses()->default()->first();

        return view('customers.show', [
            'customer'       => $customer,
            'statuses'       => CustomerStatus::cases(),
            'defaultAddress' => $defaultAddress,
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->service->update($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function changeStatus(ChangeCustomerStatusRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('changeStatus', $customer);

        $this->service->changeStatus(
            $customer,
            CustomerStatus::from($request->validated('status'))
        );

        return redirect()
            ->back()
            ->with('success', 'Customer status updated.');
    }

    public function verifyEmail(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->service->verifyEmail($customer);

        return redirect()
            ->back()
            ->with('success', 'Email marked as verified.');
    }

    /**
     * Admin sets customer portal password directly.
     * Permission: customers.update (admin + manager).
     */
    public function setPassword(SetCustomerPasswordRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->service->setPassword($customer, $request->validated('password'));

        return redirect()
            ->back()
            ->with('success', 'Customer password updated.');
    }

    /**
     * Admin sends password reset email to customer.
     * Customer clicks link and sets their own password.
     * Permission: customers.update (admin + manager).
     */
    public function sendPasswordReset(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        Password::broker('customers')->sendResetLink(['email' => $customer->email]);

        return redirect()
            ->back()
            ->with('success', 'Password reset email sent.');
    }
}
```

---

## Action Summary

| Method | Route | Auth | Service Call |
|--------|-------|------|--------------|
| `index` | GET /customers | `viewAny` | `paginate()` |
| `create` | GET /customers/create | `create` | — |
| `store` | POST /customers | `create` | `store()` |
| `show` | GET /customers/{customer} | `view` | — |
| `edit` | GET /customers/{customer}/edit | `update` | — |
| `update` | PUT /customers/{customer} | `update` | `update()` |
| `destroy` | DELETE /customers/{customer} | `delete` | `delete()` |
| `changeStatus` | PATCH /customers/{customer}/status | `changeStatus` | `changeStatus()` |
| `verifyEmail` | POST /customers/{customer}/verify-email | `update` | `verifyEmail()` |
| `setPassword` | PUT /customers/{customer}/password | `update` | `setPassword()` |
| `sendPasswordReset` | POST /customers/{customer}/password-reset | `update` | — (broker directly) |

---

## SetCustomerPasswordRequest

**File:** `app/Http/Requests/Customer/SetCustomerPasswordRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SetCustomerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

---

## Routes to Add

```php
// Inside the customers.* prefix group in web.php:
Route::post('/{customer}/verify-email',   [CustomerController::class, 'verifyEmail'])->name('verifyEmail');
Route::put('/{customer}/password',        [CustomerController::class, 'setPassword'])->name('setPassword');
Route::post('/{customer}/password-reset', [CustomerController::class, 'sendPasswordReset'])->name('sendPasswordReset');
```

---

## Rules

- Every action calls `$this->authorize()` — no exceptions
- `setPassword()` and `sendPasswordReset()` reuse `customers.update` permission — no new permission needed
- `sendPasswordReset()` uses `Password::broker('customers')` — NOT the default broker (which targets `users` table)
- `show()` passes `$defaultAddress` from controller — never resolve in Blade
