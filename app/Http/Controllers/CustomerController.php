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

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->service->paginate(
            $request->only(['search', 'status'])
        );

        return view('customers.index', [
            'customers' => $customers,
            'statuses' => CustomerStatus::cases(),
            'filters' => $request->only(['search', 'status']),
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

        return view('customers.show', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
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
}
