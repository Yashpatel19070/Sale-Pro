<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $service) {}

    public function create(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('create', Invoice::class);

        return view('invoices.create', compact('purchaseOrder'));
    }

    public function store(StoreInvoiceRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        try {
            $this->service->store($purchaseOrder, $request->validated());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Invoice added.');
    }

    public function show(PurchaseOrder $purchaseOrder, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['purchaseOrder.supplier', 'approvedBy']);

        return view('invoices.show', compact('purchaseOrder', 'invoice'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, Invoice $invoice): RedirectResponse
    {
        $this->authorize('approve', $invoice);
        try {
            $this->service->approve($invoice, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Invoice approved.');
    }

    public function markPaid(PurchaseOrder $purchaseOrder, Invoice $invoice): RedirectResponse
    {
        $this->authorize('markPaid', $invoice);
        $invoice->load('purchaseOrder');
        try {
            $this->service->markPaid($invoice);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Invoice marked as paid.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);
        try {
            $this->service->delete($invoice);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Invoice deleted.');
    }
}
