# Customer Module — Controller

## File: `app/Http/Controllers/CustomerController.php`

```php
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
```

---

## Routes: `routes/web.php`

All customer routes sit inside the existing `auth + load_perms + verified + active` group.
Convention: static routes first, dynamic `{customer}` routes after.
Restore uses `{trashedCustomer}` — matches existing `{trashedUser}` / `{trashedDepartment}` convention.

```php
use App\Http\Controllers\CustomerController;

// ── Customers ─────────────────────────────────────────────────────────────────
// Static routes MUST come before /{customer} or Laravel captures them as an ID.
Route::get('customers',               [CustomerController::class, 'index'])  ->name('customers.index');
Route::get('customers/create',        [CustomerController::class, 'create']) ->name('customers.create');
Route::post('customers',              [CustomerController::class, 'store'])  ->name('customers.store');

// Dynamic routes
Route::get('customers/{customer}',          [CustomerController::class, 'show'])    ->name('customers.show');
Route::get('customers/{customer}/edit',     [CustomerController::class, 'edit'])    ->name('customers.edit');
Route::put('customers/{customer}',          [CustomerController::class, 'update'])  ->name('customers.update');
Route::delete('customers/{customer}',       [CustomerController::class, 'destroy']) ->name('customers.destroy');

// Custom actions on existing records
Route::post('customers/{customer}/assign',  [CustomerController::class, 'assign'])       ->name('customers.assign');
Route::post('customers/{customer}/status',  [CustomerController::class, 'changeStatus']) ->name('customers.change-status');

// Restore — {trashedCustomer} resolved via Route::bind in AppServiceProvider (no ->withTrashed() needed)
Route::post('customers/{trashedCustomer}/restore', [CustomerController::class, 'restore'])
    ->name('customers.restore');
```

> **`{trashedCustomer}` binding**: Resolved by `Route::bind('trashedCustomer', ...)` in
> `AppServiceProvider::boot()` — the same pattern used for `{trashedUser}` and
> `{trashedDepartment}`. No `->withTrashed()` on the route. See `02-model.md` for the
> full `boot()` block including this binding.

---

## Navigation Link

In `resources/views/layouts/navigation.blade.php`:

Add in **both** the desktop nav section AND the responsive (mobile) section.
Uses `hasAnyRole()` — the same pattern as Users and Departments in the existing nav
(navigation.blade.php:18 and :86). Sales is included because they can view assigned customers.

```blade
{{-- Desktop nav — after the Departments link --}}
@if(auth()->user()->hasAnyRole(['admin', 'manager', 'sales']))
    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
        {{ __('Customers') }}
    </x-nav-link>
@endif

{{-- Responsive / mobile nav — after the Departments responsive link --}}
@if(auth()->user()->hasAnyRole(['admin', 'manager', 'sales']))
    <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
        {{ __('Customers') }}
    </x-responsive-nav-link>
@endif
```
