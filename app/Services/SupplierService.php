<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoStatus;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Supplier::withTrashed()
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($inner) use ($filters): void {
                    $inner->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('code', 'like', '%'.$filters['search'].'%');
                })
            )
            ->when(
                ($filters['status'] ?? null) === 'active',
                fn ($q) => $q->whereNull('deleted_at')
            )
            ->when(
                ($filters['status'] ?? null) === 'inactive',
                fn ($q) => $q->whereNotNull('deleted_at')
            )
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array{name: string, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, address?: string|null, notes?: string|null}  $data
     */
    public function create(array $data): Supplier
    {
        $supplier = new Supplier;
        $supplier->code = $this->generateCode();
        $supplier->is_active = true;
        $supplier->fill($data);
        $supplier->save();

        return $supplier;
    }

    /**
     * @param  array{name?: string, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, address?: string|null, notes?: string|null}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    /**
     * @throws \DomainException if supplier has open Purchase Orders
     */
    public function deactivate(Supplier $supplier): void
    {
        throw_if(
            $supplier->purchaseOrders()->whereIn('status', [
                PoStatus::Draft->value,
                PoStatus::Open->value,
                PoStatus::Partial->value,
            ])->exists(),
            \DomainException::class,
            'Cannot deactivate a supplier with open Purchase Orders.'
        );

        $supplier->is_active = false;
        $supplier->save();
        $supplier->delete();
    }

    public function restore(Supplier $supplier): Supplier
    {
        $supplier->restore();
        $supplier->is_active = true;
        $supplier->save();

        return $supplier;
    }

    /**
     * Counts all rows (including soft-deleted) so codes never reuse.
     */
    public function generateCode(): string
    {
        $count = Supplier::withTrashed()->count();

        return sprintf('SUP-%04d', $count + 1);
    }
}
