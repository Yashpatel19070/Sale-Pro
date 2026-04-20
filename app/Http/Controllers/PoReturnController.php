<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PoType;
use App\Http\Requests\PoReturn\CloseReturnPoRequest;
use App\Models\PurchaseOrder;
use App\Services\PoReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PoReturnController extends Controller
{
    public function __construct(
        private readonly PoReturnService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $returns = $this->service->list(request()->only(['search', 'status']));

        return view('po-returns.index', compact('returns'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        abort_if($purchaseOrder->type !== PoType::Return, 404);
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'lines.product', 'parentPo.supplier', 'createdBy']);

        return view('po-returns.show', compact('purchaseOrder'));
    }

    public function close(CloseReturnPoRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->type !== PoType::Return, 404);

        try {
            $this->service->close($purchaseOrder);
        } catch (\DomainException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return redirect()
            ->route('po-returns.show', $purchaseOrder)
            ->with('success', "{$purchaseOrder->po_number} return closed.");
    }
}
