# Department Module — Service

File: `app/Services/DepartmentService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    /**
     * Paginated list with optional search and status filter.
     *
     * @param array{search?: string, active?: bool} $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Department::with('manager:id,name')
            ->withCount(['users' => fn ($q) => $q->where('status', 'active')])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('name', 'like', "%{$filters['search']}%")
                      ->orWhere('code', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['active']),
                fn ($q) => $q->where('is_active', $filters['active'])
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new department.
     *
     * @param array{name: string, code: string, description?: string, manager_id?: int, is_active?: bool} $data
     */
    public function create(array $data): Department
    {
        return DB::transaction(function () use ($data): Department {
            return Department::create([
                'name'        => $data['name'],
                'code'        => strtoupper($data['code']),
                'description' => $data['description'] ?? null,
                'manager_id'  => $data['manager_id'] ?? null,
                'is_active'   => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update an existing department.
     *
     * @param array{name?: string, code?: string, description?: string, manager_id?: int|null, is_active?: bool} $data
     */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $department->update([
                'name'        => $data['name'] ?? $department->name,
                'code'        => isset($data['code']) ? strtoupper($data['code']) : $department->code,
                'description' => array_key_exists('description', $data) ? $data['description'] : $department->description,
                'manager_id'  => array_key_exists('manager_id', $data) ? $data['manager_id'] : $department->manager_id,
                'is_active'   => $data['is_active'] ?? $department->is_active,
            ]);

            return $department->fresh('manager');
        });
    }

    /**
     * Soft-delete a department. Fails if it has active users.
     *
     * @throws \RuntimeException
     */
    public function delete(Department $department): void
    {
        if ($department->users()->where('status', 'active')->exists()) {
            throw new \RuntimeException(
                "Cannot delete department \"{$department->name}\" — it still has active users."
            );
        }

        $department->delete();
    }

    /**
     * Restore a soft-deleted department.
     */
    public function restore(int $id): Department
    {
        $department = Department::onlyTrashed()->findOrFail($id);
        $department->restore();

        return $department;
    }

    /**
     * Toggle is_active flag.
     */
    public function toggleActive(Department $department): Department
    {
        $department->update(['is_active' => ! $department->is_active]);

        return $department->fresh();
    }

    /**
     * Active departments formatted for select dropdowns.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function dropdown()
    {
        return Department::forDropdown()->get();
    }
}
```

## Error Handling

- `delete()` throws `\RuntimeException` with a user-friendly message when
  active users exist. The controller catches this and returns back with an
  error flash.
- All multi-step writes are wrapped in `DB::transaction()`.
- The service never catches its own exceptions — let them bubble to the
  controller or the global handler.
