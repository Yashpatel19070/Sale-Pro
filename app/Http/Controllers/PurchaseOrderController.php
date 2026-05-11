<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrder\RejectPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StoreQcNotesRequest;
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
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $filters = $request->only(['search', 'status', 'supplier_id', 'date_from', 'date_to']);
        $pos = $this->service->paginate($filters);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $statuses = PurchaseOrderStatus::cases();

        return view('purchase-orders.index', compact('pos', 'suppliers', 'statuses', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);
        $suppliers = Supplier::forDropdown()->get();
        $products = Product::forDropdown()->get();

        return view('purchase-orders.create', compact('suppliers', 'products'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        $po = $this->service->store($request->validated(), $request->user());

        return redirect()->route('purchase-orders.show', $po)->with('success', "Purchase order {$po->po_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load([
            'supplier', 'lines.product',
            'goodsReceipts.lines.purchaseOrderLine', 'goodsReceipts.receivedBy',
            'invoices.approvedBy', 'createdBy', 'approvedBy',
        ]);

        $grnIds = $purchaseOrder->goodsReceipts->pluck('id')->all();
        $assignedGrnIds = $this->service->getAssignedGrnIds($grnIds);

        return view('purchase-orders.show', compact('purchaseOrder', 'assignedGrnIds'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['error' => "Cannot edit a purchase order in '{$purchaseOrder->status->label()}' status."]);
        }
        $purchaseOrder->load('lines.product');
        $suppliers = Supplier::forDropdown()->get();
        $products = Product::forDropdown()->get();

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        try {
            $this->service->update($purchaseOrder, $request->validated());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);
        $this->service->delete($purchaseOrder);

        return redirect()->route('purchase-orders.index')->with('success', "Purchase order {$purchaseOrder->po_number} deleted.");
    }

    public function restore(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('restore', $purchaseOrder);
        $this->service->restore($purchaseOrder);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order restored.');
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submit', $purchaseOrder);
        try {
            $this->service->submit($purchaseOrder);
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        try {
            $this->service->approve($purchaseOrder, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order approved.');
    }

    public function reject(RejectPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reject', $purchaseOrder);
        try {
            $this->service->reject($purchaseOrder, $request->validated()['rejection_reason']);
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order rejected.');
    }

    public function markOnTheWay(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('markOnTheWay', $purchaseOrder);
        try {
            $this->service->markOnTheWay($purchaseOrder);
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order marked as on the way.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);
        try {
            $this->service->cancel($purchaseOrder);
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order cancelled.');
    }

    public function qualityCheck(StoreQcNotesRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('qualityCheck', $purchaseOrder);

        try {
            $this->service->passQualityCheck($purchaseOrder, $request->validated()['qc_notes'] ?? null);
        } catch (\DomainException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Quality check passed. Purchase order marked as received.');
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['supplier', 'lines.product', 'createdBy', 'approvedBy']);

        return view('purchase-orders.print', compact('purchaseOrder'));
    }
}
