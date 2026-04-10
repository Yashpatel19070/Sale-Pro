# Department Module — Controller

File: `app/Http/Controllers/DepartmentController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = $this->service->list(
            filters: $request->only(['search', 'active']),
            perPage: 15,
        );

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        $managers = \App\Models\User::orderBy('name')
            ->where('status', 'active')
            ->select(['id', 'name'])
            ->get();

        return view('departments.create', compact('managers'));
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

        $managers = \App\Models\User::orderBy('name')
            ->where('status', 'active')
            ->select(['id', 'name'])
            ->get();

        return view('departments.edit', compact('department', 'managers'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $this->service->update($department, $request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', "Department updated.");
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

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('restore', Department::class);

        $department = $this->service->restore($id);

        return redirect()
            ->route('departments.show', $department)
            ->with('success', "Department \"{$department->name}\" restored.");
    }

    public function toggleActive(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department = $this->service->toggleActive($department);
        $state = $department->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Department {$state}.");
    }
}
```

## Routes

In `routes/web.php`, inside the `auth` + `verified` middleware group:

```php
use App\Http\Controllers\DepartmentController;

Route::resource('departments', DepartmentController::class);
Route::post('departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive'])
    ->name('departments.toggle-active');
Route::post('departments/{id}/restore', [DepartmentController::class, 'restore'])
    ->name('departments.restore');
```

## Named Routes Summary

| Name                         | Method | URI                                    |
|------------------------------|--------|----------------------------------------|
| departments.index            | GET    | /departments                           |
| departments.create           | GET    | /departments/create                    |
| departments.store            | POST   | /departments                           |
| departments.show             | GET    | /departments/{department}              |
| departments.edit             | GET    | /departments/{department}/edit         |
| departments.update           | PUT    | /departments/{department}              |
| departments.destroy          | DELETE | /departments/{department}              |
| departments.toggle-active    | POST   | /departments/{department}/toggle-active|
| departments.restore          | POST   | /departments/{id}/restore              |
