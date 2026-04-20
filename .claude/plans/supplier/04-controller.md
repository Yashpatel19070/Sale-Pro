# Supplier Module — Controller

**File:** `app/Http/Controllers/SupplierController.php`

---

## Full Controller Code

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SupplierStatus;
use App\Http\Requests\Supplier\ChangeSupplierStatusRequest;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    /**
     * GET /suppliers
     * List all suppliers — paginated, searchable, filterable by status.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->service->paginate(
            $request->only(['search', 'status'])
        );

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'statuses'  => SupplierStatus::cases(),
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    /**
     * GET /suppliers/create
     * Show the create supplier form.
     */
    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create', [
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    /**
     * POST /suppliers
     * Store a new supplier.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $this->service->store($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    /**
     * GET /suppliers/{supplier}
     * View a single supplier profile.
     */
    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    /**
     * GET /suppliers/{supplier}/edit
     * Show the edit supplier form.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', [
            'supplier' => $supplier,
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    /**
     * PUT /suppliers/{supplier}
     * Update an existing supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated successfully.');
    }

    /**
     * DELETE /suppliers/{supplier}
     * Soft-delete a supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $this->service->delete($supplier);
        } catch (\DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    /**
     * PATCH /suppliers/{supplier}/status
     * Change the status of a supplier.
     */
    public function changeStatus(ChangeSupplierStatusRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('changeStatus', $supplier);

        $this->service->changeStatus(
            $supplier,
            SupplierStatus::from($request->validated('status'))
        );

        return redirect()
            ->back()
            ->with('success', 'Supplier status updated.');
    }
}
```

---

## Action Summary

| Method | Route | Auth | Service Call |
|--------|-------|------|--------------|
| `index` | GET /suppliers | `viewAny` | `paginate()` |
| `create` | GET /suppliers/create | `create` | — |
| `store` | POST /suppliers | `create` | `store()` |
| `show` | GET /suppliers/{supplier} | `view` | — |
| `edit` | GET /suppliers/{supplier}/edit | `update` | — |
| `update` | PUT /suppliers/{supplier} | `update` | `update()` |
| `destroy` | DELETE /suppliers/{supplier} | `delete` | `delete()` |
| `changeStatus` | PATCH /suppliers/{supplier}/status | `changeStatus` | `changeStatus()` |

---

## Rules
- Every action calls `$this->authorize()` — no exceptions
- `store()` and `update()` use typed FormRequest — validated data only
- `changeStatus()` uses `ChangeSupplierStatusRequest` — not a plain Request
- Route model binding handles `{supplier}` — no manual `Supplier::find()` in controller
- `destroy` catches `DomainException` only — lets everything else bubble
- Flash messages: `with('success', '...')` for success, `with('error', '...')` for DomainException
- Redirect after write: `store` → index, `update` → show, `destroy` → index, `changeStatus` → back
