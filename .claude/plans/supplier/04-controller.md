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

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = $request->only(['search', 'status']);

        return view('suppliers.index', [
            'suppliers' => $this->service->paginate($filters),
            'statuses'  => SupplierStatus::cases(),
            'filters'   => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create', [
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', [
            'supplier' => $supplier,
            'statuses' => SupplierStatus::cases(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated successfully.');
    }

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

    public function restore(Supplier $supplier): RedirectResponse
    {
        $this->authorize('restore', $supplier);

        $this->service->restore($supplier);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier restored successfully.');
    }

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

| Method | Route | Auth source | Service Call |
|--------|-------|-------------|--------------|
| `index` | GET /suppliers | controller `authorize()` | `paginate()` |
| `create` | GET /suppliers/create | controller `authorize()` | — |
| `store` | POST /suppliers | FormRequest `authorize()` | `store()` |
| `show` | GET /suppliers/{supplier} | controller `authorize()` | — |
| `edit` | GET /suppliers/{supplier}/edit | controller `authorize()` | — |
| `update` | PUT /suppliers/{supplier} | FormRequest `authorize()` | `update()` |
| `destroy` | DELETE /suppliers/{supplier} | controller `authorize()` | `delete()` |
| `restore` | POST /suppliers/{supplier}/restore | controller `authorize()` | `restore()` |
| `changeStatus` | PATCH /suppliers/{supplier}/status | FormRequest + controller `authorize()` | `changeStatus()` |

---

## Authorization Pattern

- `store` and `update` — authorization handled entirely in the FormRequest (`authorize()` returns policy result). Controller does **not** duplicate the check.
- `changeStatus` — authorization in both FormRequest and controller (double-gate by design).
- All other actions — controller calls `$this->authorize()` directly.

```php
// authorize() calls in controller:
$this->authorize('viewAny', Supplier::class);   // index
$this->authorize('create', Supplier::class);    // create
$this->authorize('view', $supplier);            // show
$this->authorize('update', $supplier);          // edit
$this->authorize('delete', $supplier);          // destroy
$this->authorize('restore', $supplier);         // restore
$this->authorize('changeStatus', $supplier);    // changeStatus
```

---

## Rules
- `store` and `update` must NOT duplicate `$this->authorize()` — FormRequest already gates them
- `index()` extracts `$filters` once and passes to both `paginate()` and view — no double `$request->only()`
- `store()` and `update()` use typed FormRequest — validated data only
- Route model binding handles `{supplier}` — no manual `Supplier::find()` in controller
- `destroy` catches `DomainException` only — lets everything else bubble
- Flash messages: `with('success', '...')` for success, `with('error', '...')` for DomainException
- Redirect after write: `store` → index, `update` → show, `destroy` → index, `restore` → index, `changeStatus` → back
