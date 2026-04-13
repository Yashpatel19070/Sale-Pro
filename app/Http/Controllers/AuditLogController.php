<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $activities = $this->service->list(
            $request->only(['log_name', 'subject_type', 'causer_id', 'event', 'date_from', 'date_to'])
        );
        $subjectTypes = AuditLogService::SUBJECT_TYPES;
        $events = AuditLogService::EVENTS;
        $causers = User::orderBy('name')->get(['id', 'name']);

        return view('audit_log.index', compact('activities', 'subjectTypes', 'events', 'causers'));
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $activity->load('causer');

        return view('audit_log.show', compact('activity'));
    }
}
