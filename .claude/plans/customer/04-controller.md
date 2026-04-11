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
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    /**
     * GET /customers
     * List all customers — paginated, searchable, filterable by status.
     */
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

    /**
     * GET /customers/create
     * Show the create customer form.
     */
    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'statuses' => CustomerStatus::cases(),
        ]);
    }

    /**
     * POST /customers
     * Store a new customer.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $this->service->store($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * GET /customers/{customer}
     * View a single customer profile.
     */
    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        return view('customers.show', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
        ]);
    }

    /**
     * GET /customers/{customer}/edit
     * Show the edit customer form.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
        ]);
    }

    /**
     * PUT /customers/{customer}
     * Update an existing customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->service->update($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * DELETE /customers/{customer}
     * Soft-delete a customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /**
     * PATCH /customers/{customer}/status
     * Change the status of a customer.
     */
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

---

## Rules
- Every action calls `$this->authorize()` — no exceptions
- `store()` and `update()` use typed FormRequest — validated data only
- `changeStatus()` uses `ChangeCustomerStatusRequest` — not a plain Request
- Route model binding handles `{customer}` — no manual `Customer::find()` in controller
- Flash messages: use `with('success', '...')` for all successful operations
- Redirect after write: `store` → index, `update` → show, `destroy` → index, `changeStatus` → back
