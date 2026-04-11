<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->service->list(
            actor:   $request->user(),
            filters: $request->only(['search', 'status', 'source', 'assigned_to', 'department_id']),
        );

        return view('customers.index', [
            'customers'   => $customers,
            'statuses'    => CustomerStatus::cases(),
            'sources'     => CustomerSource::cases(),
            'salesUsers'  => User::role('sales')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'statuses'    => CustomerStatus::cases(),
            'sources'     => CustomerSource::cases(),
            'salesUsers'  => User::role('sales')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->service->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load([
            'assignedTo:id,name',
            'department:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return view('customers.show', compact('customer'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $customer->load(['assignedTo:id,name', 'department:id,name']);

        return view('customers.edit', [
            'customer'    => $customer,
            'statuses'    => CustomerStatus::cases(),
            'sources'     => CustomerSource::cases(),
            'salesUsers'  => User::role('sales')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    // ── Delete / Restore ──────────────────────────────────────────────────────

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted.');
    }

    public function restore(Customer $trashedCustomer): RedirectResponse
    {
        $this->authorize('restore', $trashedCustomer);

        $this->service->restore($trashedCustomer);

        return redirect()
            ->route('customers.show', $trashedCustomer)
            ->with('success', 'Customer restored.');
    }

    // ── Assign ────────────────────────────────────────────────────────────────

    public function assign(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('assign', $customer);

        $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        // null clears the assignment; non-zero sets it
        $userId = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;

        $this->service->assign($customer, $userId);

        return back()->with('success', 'Customer assigned.');
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function changeStatus(Request $request, Customer $customer): RedirectResponse
    {
        // Uses dedicated 'changeStatus' policy method — sales CANNOT change status
        $this->authorize('changeStatus', $customer);

        $request->validate([
            'status' => ['required', 'in:lead,prospect,active,churned'],
        ]);

        $this->service->changeStatus(
            $customer,
            CustomerStatus::from($request->string('status')->toString()),
        );

        return back()->with('success', 'Status updated.');
    }

    // ── Future: Import / Export ───────────────────────────────────────────────
    // importForm() and import() will be added when the ImportExport module is built.
}
