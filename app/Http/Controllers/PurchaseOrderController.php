<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\CancelPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $pos = $this->service->list($request->only(['search', 'status', 'supplier_id', 'type']));

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
        $products = Product::orderBy('name')->get(['id', 'sku', 'name']);

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
        $products = Product::orderBy('name')->get(['id', 'sku', 'name']);

        $existingLines = $purchaseOrder->lines->map(fn ($l) => [
            'product_id' => $l->product_id,
            'qty_ordered' => $l->qty_ordered,
            'unit_price' => $l->unit_price,
        ])->toArray();

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'products', 'existingLines'));
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
        try {
            $this->service->cancel($purchaseOrder, $request->validated()['cancel_notes']);
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} cancelled.");
    }

    public function reopen(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reopen', $purchaseOrder);

        try {
            $this->service->reopen($purchaseOrder, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} reopened.");
    }
}
