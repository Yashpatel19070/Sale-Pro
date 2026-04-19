# PO Return Module — Service

## PoReturnService

```php
<?php
// app/Services/PoReturnService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PoReturnService
{
    /**
     * Auto-create a return PO for a failed unit job.
     * Called by PipelineService::fail() inside a DB::transaction.
     *
     * DO NOT add DB::transaction here — this method is always called from
     * PipelineService::fail() which already wraps everything in a transaction.
     * Adding a nested transaction would not error but is redundant and misleading.
     *
     * @throws \Throwable
     */
    public function createForFailedUnit(PoUnitJob $job, User $user): PurchaseOrder
    {
        $job->load(['purchaseOrder.supplier', 'poLine']);

        $originalPo = $job->purchaseOrder;
        $originalLine = $job->poLine;

        $returnPo = PurchaseOrder::create([
            'po_number'          => $this->generateReturnPoNumber(),
            'type'               => PoType::Return,
            'parent_po_id'       => $originalPo->id,
            'supplier_id'        => $originalPo->supplier_id,
            'status'             => PoStatus::Open,
            'skip_tech'          => false,
            'skip_qa'            => false,
            'reopen_count'       => 0,
            'notes'              => "Return for failed unit in job #{$job->id} at stage {$job->current_stage->value}.",
            'created_by_user_id' => $user->id,
            'confirmed_at'       => now(),
        ]);

        $returnPo->lines()->create([
            'product_id'   => $originalLine->product_id,
            'qty_ordered'  => 1,
            'qty_received' => 0,
            'unit_price'   => $originalLine->unit_price,
        ]);

        return $returnPo->load(['supplier', 'lines.product', 'parentPo']);
    }

    /**
     * Paginated list of all return POs.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::ofType(PoType::Return)
            ->with(['supplier', 'lines.product', 'parentPo', 'createdBy'])
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($inner) use ($filters): void {
                    $inner->where('po_number', 'like', '%'.$filters['search'].'%')
                          ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$filters['search'].'%'));
                })
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Close a return PO — marks it resolved after supplier confirms receipt.
     *
     * @throws \DomainException
     */
    public function close(PurchaseOrder $returnPo): PurchaseOrder
    {
        throw_if(
            $returnPo->type !== PoType::Return,
            \DomainException::class,
            'Only return POs can be closed via this method.'
        );

        throw_if(
            $returnPo->status !== PoStatus::Open,
            \DomainException::class,
            "Return PO is already {$returnPo->status->value}."
        );

        $returnPo->update([
            'status'    => PoStatus::Closed,
            'closed_at' => now(),
        ]);

        return $returnPo->fresh();
    }

    /**
     * Generate a return PO number. Same format as regular POs (PO-YYYY-XXXX).
     * Shares the same sequential counter — the type distinguishes them, not the number.
     */
    public function generateReturnPoNumber(): string
    {
        $year  = now()->year;
        $count = PurchaseOrder::whereYear('created_at', $year)->count();

        return sprintf('PO-%d-%04d', $year, $count + 1);
    }
}
```

---

## CloseReturnPoRequest

```php
<?php
// app/Http/Requests/PoReturn/CloseReturnPoRequest.php

declare(strict_types=1);

namespace App\Http\Requests\PoReturn;

use Illuminate\Foundation\Http\FormRequest;

class CloseReturnPoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return []; // No input needed — closing requires no user data
    }
}
```

---

## PoReturnController

```php
<?php
// app/Http/Controllers/PoReturnController.php

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
        $this->authorize('viewAny', PurchaseOrder::class); // reuse PO viewAny permission

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
        // authorization handled by CloseReturnPoRequest

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
```

---

## Routes

```php
// In routes/web.php — inside admin auth middleware group:

use App\Http\Controllers\PoReturnController;

Route::prefix('po-returns')->name('po-returns.')->group(function () {
    Route::get('/', [PoReturnController::class, 'index'])->name('index');
    Route::get('/{purchaseOrder}', [PoReturnController::class, 'show'])->name('show');
    Route::post('/{purchaseOrder}/close', [PoReturnController::class, 'close'])->name('close');
});
```

---

## Method Summary

| Method | Description |
|--------|-------------|
| `createForFailedUnit(job, user)` | Auto-creates return PO from failed unit job. Called by PipelineService. No separate transaction — called inside PipelineService::fail() transaction. |
| `list(filters)` | Paginated list of type=return POs. Filters: search, status. |
| `close(returnPo)` | Open → Closed. Sets closed_at. Validates type=return and status=open. |
| `generateReturnPoNumber()` | Same format as PO numbers. Shares year-scoped counter. |

---

## Notes

- `createForFailedUnit()` does not wrap in its own `DB::transaction` — it is always called
  from inside `PipelineService::fail()` which already has a transaction.
- `close()` permission reuses `PURCHASE_ORDERS_CANCEL` in policy — same managers who cancel
  POs can close return POs. Add `PO_RETURNS_CLOSE` constant only if fine-grained control needed.
- `show()` uses `abort_if($purchaseOrder->type !== PoType::Return, 404)` to prevent the regular
  PO show route from resolving to a return PO (shared model, different controllers).
- Return PO number shares the same counter as purchase POs — `PO-2026-0005` could be either
  type. The `type` column distinguishes them in the UI.
