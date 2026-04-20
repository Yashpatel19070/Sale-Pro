# Supplier Module — Service

**File:** `app/Services/SupplierService.php`

The service handles all business logic. The controller calls the service — never touches the model directly.

---

## Full Service Code

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    /**
     * Return a paginated list of suppliers.
     * Supports optional search (name / email / contact_name) and status filter.
     *
     * @param array{search?: string, status?: string} $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Supplier::query()
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
     * Create a new supplier.
     *
     * @param array<string, mixed> $data — from StoreSupplierRequest::validated()
     */
    public function store(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * Update an existing supplier.
     *
     * @param array<string, mixed> $data — from UpdateSupplierRequest::validated()
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    /**
     * Change the status of a supplier.
     */
    public function changeStatus(Supplier $supplier, SupplierStatus $status): Supplier
    {
        $supplier->update(['status' => $status->value]);

        return $supplier->fresh();
    }

    /**
     * Soft-delete a supplier.
     * Record is NOT permanently removed — deleted_at is set.
     * Throws DomainException if supplier has any purchase orders.
     *
     * @throws \DomainException
     */
    public function delete(Supplier $supplier): void
    {
        // Guard: cannot delete if purchase orders exist.
        // When PO module is built, replace this with:
        // if ($supplier->purchaseOrders()->exists()) {
        //     throw new \DomainException('Cannot delete a supplier that has purchase orders.');
        // }

        $supplier->delete();
    }
}
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `paginate(array $filters)` | `search`, `status` keys | `LengthAwarePaginator` | 20 per page, preserves query string |
| `store(array $data)` | validated array | `Supplier` | Calls `Supplier::create()` |
| `update(Supplier, array $data)` | model + validated array | `Supplier` (fresh) | Returns refreshed model |
| `changeStatus(Supplier, SupplierStatus)` | model + enum | `Supplier` (fresh) | Stores enum value string |
| `delete(Supplier)` | model | void | Soft delete; DomainException guard for POs |

---

## Rules
- Never call `$request->all()` — data must come pre-validated from the controller
- `store()` and `update()` only receive `$request->validated()` output
- `delete()` is always soft delete — no `forceDelete()` anywhere in this module
- `paginate()` uses `withQueryString()` so search/filter params survive pagination links
- `delete()` PO guard is stubbed with a comment — activate when PO module is built

---

## Future Extension (PO Module)
When PO module is implemented, update `delete()`:
```php
if ($supplier->purchaseOrders()->exists()) {
    throw new \DomainException('Cannot delete a supplier that has purchase orders.');
}
```
Controller already catches `DomainException` — no controller change needed.
