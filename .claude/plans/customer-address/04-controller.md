# Customer Address Module — Controller

**File:** `app/Http/Controllers/CustomerAddressController.php`

Sub-resource of customers. Every action is scoped to a `{customer}` via route model binding.
No `show` action — address details are visible from the index table and edit form.

---

## Full Controller Code

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CustomerAddress\DeleteCustomerAddressRequest;
use App\Http\Requests\CustomerAddress\SetDefaultCustomerAddressRequest;
use App\Http\Requests\CustomerAddress\StoreCustomerAddressRequest;
use App\Http\Requests\CustomerAddress\UpdateCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function __construct(private readonly CustomerAddressService $service) {}

    public function index(Customer $customer): View
    {
        $this->authorize('viewAny', [CustomerAddress::class, $customer]);

        $addresses = $this->service->list($customer);

        return view('customer-addresses.index', [
            'customer' => $customer,
            'addresses' => $addresses,
        ]);
    }

    public function create(Customer $customer): View
    {
        $this->authorize('create', [CustomerAddress::class, $customer]);

        return view('customer-addresses.create', [
            'customer' => $customer,
        ]);
    }

    public function store(StoreCustomerAddressRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', [CustomerAddress::class, $customer]);

        $this->service->store($customer, $request->validated());

        return redirect()
            ->route('customer-addresses.index', $customer)
            ->with('success', 'Address added successfully.');
    }

    public function edit(Customer $customer, CustomerAddress $address): View
    {
        $this->authorize('update', [$address, $customer]);

        return view('customer-addresses.edit', [
            'customer' => $customer,
            'address' => $address,
        ]);
    }

    public function update(
        UpdateCustomerAddressRequest $request,
        Customer $customer,
        CustomerAddress $address,
    ): RedirectResponse {
        $this->authorize('update', [$address, $customer]);

        $this->service->update($address, $request->validated());

        return redirect()
            ->route('customer-addresses.index', $customer)
            ->with('success', 'Address updated successfully.');
    }

    public function destroy(DeleteCustomerAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('delete', [$address, $customer]);

        try {
            $this->service->delete($address);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customer-addresses.index', $customer)
            ->with('success', 'Address deleted.');
    }

    public function setDefault(SetDefaultCustomerAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('setDefault', [$address, $customer]);

        $this->service->setDefault($address);

        return redirect()
            ->route('customer-addresses.index', $customer)
            ->with('success', 'Default address updated.');
    }
}
```

---

## Action Summary

| Method | HTTP | URL | `authorize()` call | Service call |
|--------|------|-----|--------------------|--------------|
| `index` | GET | `/{customer}/addresses` | `viewAny, [CustomerAddress::class, $customer]` | `list($customer)` |
| `create` | GET | `/{customer}/addresses/create` | `create, [CustomerAddress::class, $customer]` | — |
| `store` | POST | `/{customer}/addresses` | `create, [CustomerAddress::class, $customer]` | `store($customer, $data)` |
| `edit` | GET | `/{customer}/addresses/{address}/edit` | `update, [$address, $customer]` | — |
| `update` | PUT | `/{customer}/addresses/{address}` | `update, [$address, $customer]` | `update($address, $data)` |
| `destroy` | DELETE | `/{customer}/addresses/{address}` | `delete, [$address, $customer]` | `delete($address)` — redirects back with error if address is default |
| `setDefault` | PATCH | `/{customer}/addresses/{address}/default` | `setDefault, [$address, $customer]` | `setDefault($address)` |

---

## `authorize()` argument shapes — why they differ

| Shape | When used | Policy method signature |
|-------|-----------|------------------------|
| `[CustomerAddress::class, $customer]` | No address model yet — `viewAny`, `create` | `(User $user, Customer $customer)` |
| `[$address, $customer]` | Address model exists — `update`, `delete`, `setDefault` | `(User $user, CustomerAddress $address, Customer $customer)` |

Laravel resolves the policy from the first argument. The second argument is passed as an extra
to the policy method. This allows the policy to verify `$address->customer_id === $customer->id`.

---

## Rules

- No `show` action — index table + edit form provide all address details
- All write actions redirect to `customer-addresses.index` (not `customers.show`) — keeps admin on addresses tab
- Route model binding resolves `{customer}` → `Customer` and `{address}` → `CustomerAddress` automatically
- Policy ownership check (`$address->customer_id === $customer->id`) happens inside the policy — not in controller
- `destroy` and `setDefault` inject `DeleteCustomerAddressRequest` / `SetDefaultCustomerAddressRequest` — FormRequest handles authorization before controller runs
- Read-only actions (`index`, `create`, `edit`) have no FormRequest injection
