<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    /**
     * @param  array{search?: string, active?: string}  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max(1, $perPage), 100);

        return Department::with('manager:id,name')
            ->withCount(['users'])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where(function ($q) use ($filters): void {
                    $q->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['active'])
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{name: string, code: string, description?: string, manager_id?: int, is_active?: bool}  $data
     */
    public function create(array $data): Department
    {
        return Department::create([
            ...$data,
            'code'      => strtoupper($data['code']),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{name?: string, code?: string, description?: string, manager_id?: int|null, is_active?: bool}  $data
     */
    public function update(Department $department, array $data): Department
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $department->update($data);

        return $department->refresh()->load('manager');
    }

    /**
     * @throws \RuntimeException
     */
    public function delete(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            if ($department->users()->where('status', 'active')->exists()) {
                throw new \RuntimeException(
                    "Cannot delete department \"{$department->name}\" — it still has active users."
                );
            }

            $department->delete();
        });
    }

    public function restore(Department $department): Department
    {
        $department->restore();

        return $department;
    }

    public function toggleActive(Department $department): Department
    {
        $department->update(['is_active' => ! $department->is_active]);

        return $department->refresh();
    }

    public function dropdown(): Collection
    {
        return Department::forDropdown()->get();
    }
}
