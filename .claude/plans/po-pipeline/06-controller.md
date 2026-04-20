# PO Pipeline Module — Controller

## PipelineController

```php
<?php
// app/Http/Controllers/PipelineController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Http\Requests\Pipeline\CreateUnitJobRequest;
use App\Http\Requests\Pipeline\FailUnitJobRequest;
use App\Http\Requests\Pipeline\PassUnitJobRequest;
use App\Http\Requests\Pipeline\StartUnitJobRequest;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Services\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function __construct(
        private readonly PipelineService $service,
    ) {}

    /**
     * My queue — jobs at stages the authenticated user can act on.
     */
    public function queue(): View
    {
        $this->authorize('viewAny', PoUnitJob::class);

        $stages = $this->stagesForUser(request()->user());

        $jobs = $this->service->queue([
            'stages'             => $stages,
            'purchase_order_id'  => request('purchase_order_id'),
        ]);

        return view('pipeline.queue', compact('jobs', 'stages'));
    }

    /**
     * Detail view for a single unit job — shows event history.
     */
    public function show(PoUnitJob $unitJob): View
    {
        $this->authorize('view', $unitJob);

        $unitJob->load([
            'purchaseOrder.supplier',
            'poLine.product',
            'inventorySerial',
            'assignedTo',
            'events.user',
        ]);

        return view('pipeline.show', compact('unitJob'));
    }

    /**
     * Claim a pending unit job. Sets status to in_progress, assigns authenticated user.
     * Redirects to the job detail page where the worker completes Pass or Fail.
     */
    public function start(StartUnitJobRequest $request, PoUnitJob $unitJob): RedirectResponse
    {
        try {
            $job = $this->service->start($unitJob, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        return redirect()
            ->route('pipeline.show', $job)
            ->with('success', 'Job claimed. Complete the inspection below.');
    }

    /**
     * Create a unit job for a PO line (receive a unit into the pipeline).
     */
    public function store(CreateUnitJobRequest $request): RedirectResponse
    {
        $line = PoLine::with('purchaseOrder')->findOrFail($request->validated()['po_line_id']);

        try {
            $job = $this->service->createJob($line, $request->user(), $request->validated()['notes'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        return redirect()
            ->route('pipeline.show', $job)
            ->with('success', 'Unit received into pipeline.');
    }

    /**
     * Pass a unit at its current stage.
     */
    public function pass(PassUnitJobRequest $request, PoUnitJob $unitJob): RedirectResponse
    {
        try {
            $job = $this->service->pass($unitJob, $request->user(), $request->validated());
        } catch (\DomainException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        $nextStage = $job->current_stage->label();

        return redirect()
            ->route('pipeline.queue')
            ->with('success', "Unit passed. Now at: {$nextStage}.");
    }

    /**
     * Fail a unit at its current stage.
     */
    public function fail(FailUnitJobRequest $request, PoUnitJob $unitJob): RedirectResponse
    {
        try {
            $this->service->fail($unitJob, $request->user(), $request->validated()['notes']);
        } catch (\DomainException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        return redirect()
            ->route('pipeline.queue')
            ->with('success', 'Unit marked as failed. Return PO created.');
    }

    /**
     * Determine which pipeline stages the authenticated user can act on.
     *
     * @return array<PipelineStage>
     */
    private function stagesForUser(\App\Models\User $user): array
    {
        // NOTE: pipeline.receive is intentionally excluded.
        // Procurement receives units via the PO show page (PurchaseOrderController::show),
        // not via this queue. createJob() immediately advances past receive to visual —
        // there are never pending receive-stage jobs sitting in the queue.
        $map = [
            'pipeline.visual'        => PipelineStage::Visual,
            'pipeline.serial_assign' => PipelineStage::SerialAssign,
            'pipeline.tech'          => PipelineStage::Tech,
            'pipeline.qa'            => PipelineStage::Qa,
            'pipeline.shelf'         => PipelineStage::Shelf,
        ];

        return array_values(array_filter(
            $map,
            fn (PipelineStage $stage, string $permission) => $user->can($permission),
            ARRAY_FILTER_USE_BOTH
        ));
    }
}
```

---

## Routes

```php
// In routes/web.php — inside admin auth middleware group:

use App\Http\Controllers\PipelineController;

Route::prefix('pipeline')->name('pipeline.')->group(function () {
    Route::get('/', [PipelineController::class, 'queue'])->name('queue');
    Route::post('/jobs', [PipelineController::class, 'store'])->name('store');
    Route::get('/jobs/{unitJob}', [PipelineController::class, 'show'])->name('show');
    Route::post('/jobs/{unitJob}/start', [PipelineController::class, 'start'])->name('start');
    Route::post('/jobs/{unitJob}/pass', [PipelineController::class, 'pass'])->name('pass');
    Route::post('/jobs/{unitJob}/fail', [PipelineController::class, 'fail'])->name('fail');
});
```

---

## Views Required

| View | Path | Notes |
|------|------|-------|
| queue | `resources/views/pipeline/queue.blade.php` | Table of pending jobs for user's stages. Columns: PO number, product, serial (if assigned), current stage. Action button: **Take** (POST to `pipeline.start`). No Pass/Fail here — those are on the detail page after claiming. |
| show | `resources/views/pipeline/show.blade.php` | Job detail: PO info, product, serial, current stage, assigned worker. Event history timeline (chronological). **Pass** and **Fail** buttons shown only when `status === in_progress AND assignedTo === auth user`. Pending jobs show no action buttons. |

---

## Notes

- Worker flow: queue shows "Take" button → POST to `pipeline.start` → redirect to `pipeline.show` → Pass or Fail from detail page.
- `queue()` calls `stagesForUser()` to filter jobs to only the stages the user can act on. `pipeline.receive` excluded — procurement entry point is the PO show page, not this queue.
- Manager/Admin have `pipeline.viewAny` so `stagesForUser()` returns `[]` for them. When stages is empty, the `when(!empty(...))` guard in `PipelineService::queue()` skips the `whereIn` filter — managers see ALL pending jobs across all stages (no stage restriction).
- `start()` redirects to `pipeline.show` — worker completes the job on the detail page.
- `pass()` and `fail()` both redirect back to `pipeline.queue` — worker returns to queue for their next job.
- `fail()` redirects to queue with a message confirming Return PO was created.
- DomainException from service → `back()->withErrors(['job' => $e->getMessage()])`.
- Route param is `unitJob` (camelCase) to match Laravel resource convention.

---

## Implementation Deviations (actual code differs from plan above)

### `queue()` — injects `Request` instead of using `request()` helper
Plan used `request()->user()` (returns `?Authenticatable`, not `User`). Actual code injects `Request $request` and casts with a `@var` docblock:
```php
public function queue(Request $request): View
{
    /** @var User $user */
    $user = $request->user();
    $stages = $this->stagesForUser($user);
    $jobs = $this->service->queue([
        'stages' => $stages,
        'purchase_order_id' => $request->input('purchase_order_id'),
    ]);
}
```
Fixes PHPStan level-8 type error. `request()->user()` also replaced with typed `$request->input()`.

### `show()` — passes `$locations` to view instead of querying in Blade
`pipeline/show.blade.php` had `\App\Models\InventoryLocation::orderBy('name')->get()` inline. Moved to controller:
```php
$locations = InventoryLocation::orderBy('name')->get();
return view('pipeline.show', compact('unitJob', 'locations'));
```
Blade now uses `@foreach ($locations as $loc)`. Keeps DB queries out of view layer.
