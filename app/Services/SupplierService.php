<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Supplier::withTrashed()
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->byStatus(SupplierStatus::from($filters['status']))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $copy = $supplier->fresh();
        $copy->update($data);

        return $copy;
    }

    public function changeStatus(Supplier $supplier, SupplierStatus $status): Supplier
    {
        $copy = $supplier->fresh();
        $copy->update(['status' => $status]);

        return $copy;
    }

    public function restore(Supplier $supplier): void
    {
        $supplier->restore();
    }

    /**
     * @throws \DomainException
     */
    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
