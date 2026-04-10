<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $service) {}

    private function activeManagers(): Collection
    {
        return User::where('status', 'active')
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get();
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = $this->service->list(
            filters: $request->only(['search', 'active']),
        );

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('departments.create', ['managers' => $this->activeManagers()]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $department = $this->service->create($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', "Department \"{$department->name}\" created.");
    }

    public function show(Department $department): View
    {
        $this->authorize('view', $department);

        $department->load(['manager:id,name', 'users:id,name,status,department_id']);

        return view('departments.show', compact('department'));
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('departments.edit', [
            'department' => $department,
            'managers'   => $this->activeManagers(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->service->update($department, $request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        try {
            $this->service->delete($department);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('departments.index')
            ->with('success', "Department \"{$department->name}\" deleted.");
    }

    public function restore(Department $trashedDepartment): RedirectResponse
    {
        $this->authorize('restore', $trashedDepartment);

        $this->service->restore($trashedDepartment);

        return redirect()
            ->route('departments.show', $trashedDepartment)
            ->with('success', "Department \"{$trashedDepartment->name}\" restored.");
    }

    public function toggleActive(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department = $this->service->toggleActive($department);
        $state      = $department->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Department {$state}.");
    }
}
