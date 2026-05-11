<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GoodsReceiptStatus;
use App\Http\Requests\GoodsReceipt\StoreGoodsReceiptQcRequest;
use App\Http\Requests\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Http\Requests\GoodsReceipt\StoreGrnSerialRequest;
use App\Http\Requests\GoodsReceipt\UpdateGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use App\Services\InventoryLocationService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
        private readonly InventoryMovementService $movementService,
        private readonly InventoryLocationService $locationService,
    ) {}

    public function create(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('create', GoodsReceipt::class);
        $purchaseOrder->load(['lines.product']);

        return view('goods-receipts.create', compact('purchaseOrder'));
    }

    public function store(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', GoodsReceipt::class);
        try {
            $grn = $this->service->store($purchaseOrder, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.goods-receipts.show', [$purchaseOrder, $grn])
            ->with('success', "Goods receipt {$grn->grn_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);
        $goodsReceipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine.product', 'lines.qcInspectedBy', 'receivedBy']);

        $serialsAssigned = InventoryMovement::where('goods_receipt_id', $goodsReceipt->id)->exists();

        return view('goods-receipts.show', compact('purchaseOrder', 'goodsReceipt', 'serialsAssigned'));
    }

    public function edit(PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): View|RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        if ($goodsReceipt->status === GoodsReceiptStatus::Complete) {
            return back()->withErrors(['error' => 'Completed goods receipts cannot be edited.']);
        }
        $purchaseOrder->load(['supplier', 'lines.product']);

        return view('goods-receipts.edit', compact('purchaseOrder', 'goodsReceipt'));
    }

    public function update(UpdateGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        try {
            $this->service->update($goodsReceipt, $request->validated(), $purchaseOrder);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.goods-receipts.show', [$purchaseOrder, $goodsReceipt])
            ->with('success', 'Goods receipt updated.');
    }

    public function complete(PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        try {
            $this->service->complete($goodsReceipt, $purchaseOrder);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Goods receipt completed.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('delete', $goodsReceipt);
        try {
            $this->service->delete($goodsReceipt);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Goods receipt deleted.');
    }

    public function submitQc(StoreGoodsReceiptQcRequest $request, PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        try {
            $this->service->submitQc($goodsReceipt, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.goods-receipts.show', [$purchaseOrder, $goodsReceipt])
            ->with('success', 'QC submitted. Assign serials below.');
    }

    public function assignSerials(PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): View|RedirectResponse
    {
        $this->authorize('bulkReceive', InventoryMovement::class);

        try {
            $this->service->assertCanAssignSerials($goodsReceipt, $purchaseOrder);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $goodsReceipt->load(['lines.purchaseOrderLine.product', 'receivedBy']);
        $purchaseOrder->load(['supplier']);
        $locations = $this->locationService->activeForDropdown();

        return view('goods-receipts.assign-serials', compact('purchaseOrder', 'goodsReceipt', 'locations'));
    }

    public function storeSerials(StoreGrnSerialRequest $request, PurchaseOrder $purchaseOrder, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('bulkReceive', InventoryMovement::class);
        try {
            $serials = $this->movementService->bulkReceiveFromGrn($goodsReceipt, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $count = $serials->count();
        session(['bulk_receive_ids' => $serials->pluck('id')->toArray()]);

        return redirect()->route('inventory-movements.bulk-receive-print')
            ->with('success', "Generated {$count} serial numbers.");
    }
}
