# Supplier Module — Controller

## SupplierController

```php
<?php
// app/Http/Controllers/SupplierController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->service->list(request()->only(['search', 'status']));

        return view('suppliers.index', compact('suppliers'));
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', compact('supplier'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->service->create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->code} created.");
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $this->service->deactivate($supplier);
        } catch (\DomainException $e) {
            return back()->withErrors(['supplier' => $e->getMessage()]);
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', "{$supplier->code} deactivated.");
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $this->authorize('restore', $supplier);

        $this->service->restore($supplier);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "{$supplier->code} restored.");
    }
}
```

---

## Routes

```php
// In routes/web.php — inside admin auth middleware group:

use App\Http\Controllers\SupplierController;

Route::prefix('suppliers')->name('suppliers.')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::get('/create', [SupplierController::class, 'create'])->name('create');
    Route::post('/', [SupplierController::class, 'store'])->name('store');
    Route::get('/{supplier}', [SupplierController::class, 'show'])
        ->name('show')
        ->withTrashed();
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
    Route::patch('/{supplier}', [SupplierController::class, 'update'])->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
    Route::post('/{supplier}/restore', [SupplierController::class, 'restore'])
        ->name('restore')
        ->withTrashed();
});
```

---

## Views Required

| View | Path | Notes |
|------|------|-------|
| index | `resources/views/suppliers/index.blade.php` | Table with code, name, status badge, search/filter |
| show | `resources/views/suppliers/show.blade.php` | Full detail + PO history (once PO module exists) |
| create | `resources/views/suppliers/create.blade.php` | Includes `_form` partial |
| edit | `resources/views/suppliers/edit.blade.php` | Includes `_form` partial |
| _form | `resources/views/suppliers/_form.blade.php` | Shared form fields (name, contact_*, address, notes) |

---

## Notes

- `show` and `restore` routes use `->withTrashed()` so soft-deleted suppliers resolve via route model binding.
- `destroy` catches `DomainException` from service (open PO guard) and returns back with error — no 500.
- Flash key `'success'` used for all success messages — matches existing project convention.
- `DomainException` from `deactivate()` becomes a user-visible form error, not an exception page.
