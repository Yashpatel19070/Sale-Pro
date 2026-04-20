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
     * @param array{search?: string, status?: string} $filters
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
     * @param array<string, mixed> $data — from StoreSupplierRequest::validated()
     */
    public function store(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * @param array<string, mixed> $data — from UpdateSupplierRequest::validated()
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
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `paginate(array $filters)` | `search`, `status` keys | `LengthAwarePaginator` | `withTrashed()` — shows deleted rows on index for restore |
| `store(array $data)` | validated array | `Supplier` | Calls `Supplier::create()` |
| `update(Supplier, array $data)` | model + validated array | `Supplier` | `fresh()` before update preserves caller's original reference |
| `changeStatus(Supplier, SupplierStatus)` | model + enum | `Supplier` | Passes enum directly — cast handles value conversion |
| `restore(Supplier)` | model | void | Clears `deleted_at` via Eloquent `restore()` |
| `delete(Supplier)` | model | void | Soft delete; DomainException guard for POs (future) |

---

## Immutability Pattern

`update()` and `changeStatus()` fetch a fresh instance before writing so the caller's original model reference is never mutated:

```php
$copy = $supplier->fresh();   // new instance from DB
$copy->update($data);          // writes to DB, mutates $copy only
return $copy;                  // caller gets updated state; original $supplier unchanged
```

This is intentional — tests verify that `$supplier->name` remains unchanged after `update()` is called.

---

## Audit Logging

Audit logging is **automatic** via the `LogsActivity` trait on the `Supplier` model.
No manual logging code needed in the service.

| Event | What gets logged |
|-------|-----------------|
| `store()` | `created` — all fillable fields |
| `update()` | `updated` — only dirty (changed) fields |
| `changeStatus()` | `updated` — only `status` field (dirty only) |
| `delete()` | `deleted` — soft delete recorded |
| `restore()` | `restored` — Spatie logs the restore event |

Logs are stored in the `activity_log` table and visible in the admin Audit Log module.

**Required: add `Supplier` to `AuditLogService::SUBJECT_TYPES`** in `app/Services/AuditLogService.php`:

```php
use App\Models\Supplier;

public const SUBJECT_TYPES = [
    // ... existing entries ...
    Supplier::class => 'Supplier',
];
```

---

## Rules
- Never call `$request->all()` — data must come pre-validated from the controller
- `store()` and `update()` only receive `$request->validated()` output
- `delete()` is always soft delete — no `forceDelete()` anywhere in this module
- `paginate()` uses `withTrashed()` — deleted records appear on index (dimmed) with Restore button
- `paginate()` uses `withQueryString()` so search/filter params survive pagination links
- `changeStatus()` passes the enum directly — the `status` cast handles the conversion

---

## Future Extension (PO Module)
When PO module is implemented, update `delete()` with a transaction guard:
```php
DB::transaction(function () use ($supplier): void {
    if ($supplier->purchaseOrders()->exists()) {
        throw new \DomainException('Cannot delete a supplier that has purchase orders.');
    }
    $supplier->delete();
});
```
Controller already catches `DomainException` — no controller change needed.
