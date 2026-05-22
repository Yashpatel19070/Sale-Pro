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
