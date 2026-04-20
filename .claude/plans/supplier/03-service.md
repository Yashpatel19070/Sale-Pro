# Supplier Module — Service

## SupplierService

```php
<?php
// app/Services/SupplierService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    /**
     * Paginated supplier list with optional filters.
     *
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
                isset($filters['status']) && $filters['status'] === 'active',
                fn ($q) => $q->whereNull('deleted_at')
            )
            ->when(
                isset($filters['status']) && $filters['status'] === 'inactive',
                fn ($q) => $q->whereNotNull('deleted_at')
            )
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Create a new supplier. Code is auto-generated.
     *
     * @param  array{name: string, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, address?: string|null, notes?: string|null}  $data
     */
    public function create(array $data): Supplier
    {
        return Supplier::create([
            'code'          => $this->generateCode(),
            'name'          => $data['name'],
            'contact_name'  => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address'       => $data['address'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'is_active'     => true,
        ]);
    }

    /**
     * Update a supplier. Code is never changed.
     *
     * @param  array{name?: string, contact_name?: string|null, contact_email?: string|null, contact_phone?: string|null, address?: string|null, notes?: string|null}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name'          => $data['name'] ?? $supplier->name,
            'contact_name'  => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address'       => $data['address'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);

        return $supplier->fresh();
    }

    /**
     * Deactivate (soft-delete) a supplier.
     *
     * @throws \DomainException if supplier has open Purchase Orders
     */
    public function deactivate(Supplier $supplier): void
    {
        // Guard: cannot deactivate if there are open POs.
        // UNCOMMENT THIS BLOCK after implementing the Purchase Order module.
        // See purchase-order/00-overview.md Implementation Order → "After step 2 (Model)" note.
        // throw_if(
        //     $supplier->purchaseOrders()->whereIn('status', ['draft', 'open', 'partial'])->exists(),
        //     \DomainException::class,
        //     'Cannot deactivate a supplier with open Purchase Orders.'
        // );

        $supplier->update(['is_active' => false]);
        $supplier->delete(); // sets deleted_at
    }

    /**
     * Restore a soft-deleted supplier.
     */
    public function restore(Supplier $supplier): Supplier
    {
        $supplier->restore(); // clears deleted_at
        $supplier->update(['is_active' => true]);

        return $supplier->fresh();
    }

    /**
     * Auto-generate the next sequential supplier code.
     * Format: SUP-0001, SUP-0002, ...
     * Counts all rows including soft-deleted so codes never reuse.
     */
    public function generateCode(): string
    {
        $max = Supplier::withTrashed()->max('id') ?? 0;

        return sprintf('SUP-%04d', $max + 1);
    }
}
```

---

## Method Summary

| Method | Description |
|--------|-------------|
| `list(filters)` | Paginated list. Filters: `search` (name/code), `status` (active/inactive). Includes soft-deleted for admin visibility. |
| `create(data)` | Auto-generates code. Sets `is_active = true`. |
| `update(supplier, data)` | Updates all fields except `code`. Null-safe on optional fields. |
| `deactivate(supplier)` | Sets `is_active = false`, then soft-deletes. Guards open POs (guarded once PO module exists). |
| `restore(supplier)` | Restores soft delete, sets `is_active = true`. |
| `generateCode()` | `MAX(id)` across all rows (incl. deleted) + 1, formatted `SUP-XXXX`. |

---

## Notes

- `update()` uses `?? null` for nullable optional fields — allows intentional clearing.
- `update()` does NOT accept `code` — never allow code changes after creation.
- `deactivate()` PO guard is now active — uses `PurchaseOrder` model with `PoStatus` enum values.
- `generateCode()` uses `MAX(id)` not `MAX(code)` to avoid string-to-int parsing.
- No `DB::transaction` needed — all methods touch only one table.

---

## Implementation Deviations (actual code differs from plan above)

### `deactivate()` — PO guard now uses `PoStatus` enum (not raw strings)
PO module built. Guard uncommented and updated to enum values to satisfy PHPStan:
```php
use App\Enums\PoStatus;

$supplier->purchaseOrders()->whereIn('status', [
    PoStatus::Draft->value,
    PoStatus::Open->value,
    PoStatus::Partial->value,
])->exists()
```

### `create()` / `update()` — no significant deviations
Plan code matches actual implementation. No simplification needed beyond Pint formatting.
