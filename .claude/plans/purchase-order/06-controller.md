# Purchase Order Module — Controller

## PurchaseOrderController

```php
<?php
// app/Http/Controllers/PurchaseOrderController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\CancelPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $pos = $this->service->list(request()->only(['search', 'status', 'supplier_id', 'type']));

        return view('purchase-orders.index', compact('pos'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'lines.product', 'createdBy', 'unitJobs']);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);

        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'code', 'name']);
        $products  = Product::orderBy('name')->get(['id', 'sku', 'name']);

        return view('purchase-orders.create', compact('suppliers', 'products'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->service->create($request->validated(), $request->user());

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('success', "Purchase Order {$po->po_number} created.");
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load(['lines.product']);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'code', 'name']);
        $products  = Product::orderBy('name')->get(['id', 'sku', 'name']);

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->service->update($purchaseOrder, $request->validated());
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase Order updated.');
    }

    public function confirm(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('confirm', $purchaseOrder);

        try {
            $this->service->confirm($purchaseOrder);
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} confirmed and opened.");
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        // authorization handled by CancelPurchaseOrderRequest

        try {
            $this->service->cancel($purchaseOrder, $request->validated()['cancel_notes']);
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} cancelled.");
    }

    public function reopen(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reopen', $purchaseOrder);

        try {
            $this->service->reopen($purchaseOrder, request()->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} reopened.");
    }
}
```

---

## Routes

```php
// In routes/web.php — inside admin auth middleware group:

use App\Http\Controllers\PurchaseOrderController;

Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
    Route::patch('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
    Route::post('/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->name('confirm');
    Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
    Route::post('/{purchaseOrder}/reopen', [PurchaseOrderController::class, 'reopen'])->name('reopen');
});
```

---

## Views Required

| View | Path | Notes |
|------|------|-------|
| index | `resources/views/purchase-orders/index.blade.php` | Table: PO number, supplier, status badge, lines count, created by, created at |
| show | `resources/views/purchase-orders/show.blade.php` | Header + lines table with progress bars + unit jobs summary + action buttons |
| create | `resources/views/purchase-orders/create.blade.php` | Includes `_form` partial |
| edit | `resources/views/purchase-orders/edit.blade.php` | Includes `_form` partial |
| _form | `resources/views/purchase-orders/_form.blade.php` | Supplier dropdown, skip flags, notes, dynamic lines table (JS) |

---

## Notes

- All DomainException from service → `back()->withErrors(['po' => $e->getMessage()])`.
- `confirm`, `cancel`, `reopen` use POST routes (not DELETE/PUT) for browser form compatibility.
- `edit` view loads `lines.product` eagerly for rendering existing line items.
- `create` passes `$suppliers` (active only) and `$products` to the view for dropdowns.
- Route model binding parameter is `purchaseOrder` (camelCase) — matches controller method signature.
