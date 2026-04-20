<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\PipelineStage;
use App\Http\Requests\Pipeline\CreateUnitJobRequest;
use App\Http\Requests\Pipeline\FailUnitJobRequest;
use App\Http\Requests\Pipeline\PassUnitJobRequest;
use App\Http\Requests\Pipeline\StartUnitJobRequest;
use App\Models\InventoryLocation;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\User;
use App\Services\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function __construct(
        private readonly PipelineService $service,
    ) {}

    public function queue(Request $request): View
    {
        $this->authorize('viewAny', PoUnitJob::class);

        /** @var User $user */
        $user = $request->user();
        $stages = $this->stagesForUser($user);

        $jobs = $this->service->queue([
            'stages' => $stages,
            'purchase_order_id' => $request->input('purchase_order_id'),
        ]);

        return view('pipeline.queue', compact('jobs', 'stages'));
    }

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

        $locations = InventoryLocation::orderBy('name')->get();

        return view('pipeline.show', compact('unitJob', 'locations'));
    }

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

    /** @return array<PipelineStage> */
    private function stagesForUser(User $user): array
    {
        $map = [
            Permission::PIPELINE_VISUAL => PipelineStage::Visual,
            Permission::PIPELINE_SERIAL_ASSIGN => PipelineStage::SerialAssign,
            Permission::PIPELINE_TECH => PipelineStage::Tech,
            Permission::PIPELINE_QA => PipelineStage::Qa,
            Permission::PIPELINE_SHELF => PipelineStage::Shelf,
        ];

        return array_values(array_filter(
            $map,
            fn (PipelineStage $stage, string $permission) => $user->can($permission),
            ARRAY_FILTER_USE_BOTH
        ));
    }
}
