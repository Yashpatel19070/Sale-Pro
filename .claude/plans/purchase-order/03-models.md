# Purchase Order Module — Models

---

## Model: `PurchaseOrder`

**File:** `app/Models/PurchaseOrder.php`

### Traits
- `HasFactory`
- `SoftDeletes`
- `LogsActivity` (Spatie)

### Fillable
```
supplier_id, status, expected_delivery_date, notes,
subtotal, tax_total, grand_total,
approved_by, approved_at, rejection_reason, created_by, po_number
```

### Casts
```php
'status'                  => PurchaseOrderStatus::class,
'expected_delivery_date'  => 'date',
'approved_at'             => 'datetime',
'subtotal'                => 'decimal:2',
'tax_total'               => 'decimal:2',
'grand_total'             => 'decimal:2',
```

### Relationships
```php
supplier()       → belongsTo(Supplier::class)
lines()          → hasMany(PurchaseOrderLine::class)
goodsReceipts()  → hasMany(GoodsReceipt::class)
invoices()       → hasMany(Invoice::class)
createdBy()      → belongsTo(User::class, 'created_by')
approvedBy()     → belongsTo(User::class, 'approved_by')
```

### Scopes
```php
scopeByStatus(Builder $query, PurchaseOrderStatus $status)
scopeBySupplier(Builder $query, int $supplierId)
scopeSearch(Builder $query, string $term)  // searches po_number
scopeOverdue(Builder $query)               // expected_delivery_date < today AND status NOT IN (received, closed, cancelled)
```

### Activity Log
```php
getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logFillable()->logOnlyDirty();
}
```

---

## Model: `PurchaseOrderLine`

**File:** `app/Models/PurchaseOrderLine.php`

### Traits
- `HasFactory`

### Fillable
```
purchase_order_id, product_id, description,
qty_ordered, qty_received, qty_on_hand_snapshot,
unit_cost, tax_rate, line_total
```

### Casts
```php
'qty_ordered'          => 'decimal:2',
'qty_received'         => 'decimal:2',
'qty_on_hand_snapshot' => 'decimal:2',
'unit_cost'            => 'decimal:2',
'tax_rate'             => 'decimal:2',
'line_total'           => 'decimal:2',
```

### Relationships
```php
purchaseOrder()     → belongsTo(PurchaseOrder::class)
product()           → belongsTo(Product::class)
goodsReceiptLines() → hasMany(GoodsReceiptLine::class)
```

### Helper Methods
```php
remainingQty(): float  // qty_ordered - qty_received
isFullyReceived(): bool // qty_received >= qty_ordered
```

---

## Model: `GoodsReceipt`

**File:** `app/Models/GoodsReceipt.php`

### Traits
- `HasFactory`
- `SoftDeletes`
- `LogsActivity` (Spatie)

### Fillable
```
purchase_order_id, grn_number, received_by,
received_date, notes, status
```

### Casts
```php
'received_date' => 'date',
'status'        => GoodsReceiptStatus::class,
```

### Note on GRN Status
- `draft` — work in progress, lines editable, qty NOT yet committed to PO
- `complete` — confirmed, qty_received on PO lines updated, locked

### Relationships
```php
purchaseOrder() → belongsTo(PurchaseOrder::class)
lines()         → hasMany(GoodsReceiptLine::class)
receivedBy()    → belongsTo(User::class, 'received_by')
```

### Scopes
```php
scopeByPurchaseOrder(Builder $query, int $poId)
```

### Activity Log
```php
getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logFillable()->logOnlyDirty();
}
```

---

## Model: `GoodsReceiptLine`

**File:** `app/Models/GoodsReceiptLine.php`

### Traits
- `HasFactory`

### Fillable
```
goods_receipt_id, purchase_order_line_id, qty_received, notes
```

### Casts
```php
'qty_received' => 'decimal:2',
```

### Relationships
```php
goodsReceipt()       → belongsTo(GoodsReceipt::class)
purchaseOrderLine()  → belongsTo(PurchaseOrderLine::class)
```

---

## Model: `Invoice`

**File:** `app/Models/Invoice.php`

### Traits
- `HasFactory`
- `SoftDeletes`
- `LogsActivity` (Spatie)

### Fillable
```
purchase_order_id, invoice_number, invoice_date, due_date,
amount, status, notes, approved_by, approved_at, paid_at
```

### Casts
```php
'invoice_date' => 'date',
'due_date'     => 'date',
'approved_at'  => 'datetime',
'paid_at'      => 'datetime',
'amount'       => 'decimal:2',
'status'       => InvoiceStatus::class,
```

### Note on Invoice Status
Simple three-value enum: `pending` / `approved` / `paid`. Create `app/Enums/InvoiceStatus.php`.

### Relationships
```php
purchaseOrder() → belongsTo(PurchaseOrder::class)
approvedBy()    → belongsTo(User::class, 'approved_by')
```

### Scopes
```php
scopeByStatus(Builder $query, InvoiceStatus $status)
scopeByPurchaseOrder(Builder $query, int $poId)
scopeOverdue(Builder $query)  // due_date < today AND status != paid
```

### Activity Log
```php
getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logFillable()->logOnlyDirty();
}
```

---

## Additional Enums Needed

### `app/Enums/GoodsReceiptStatus.php`
| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Draft` | `draft` | Draft | gray |
| `Complete` | `complete` | Complete | green |

### `app/Enums/InvoiceStatus.php`
| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Pending` | `pending` | Pending | yellow |
| `Approved` | `approved` | Approved | blue |
| `Paid` | `paid` | Paid | green |

---

## AuditLogService Registration

Add all 3 loggable models to `app/Services/AuditLogService.php`:

```php
use App\Models\PurchaseOrder;
use App\Models\GoodsReceipt;
use App\Models\Invoice;

public const SUBJECT_TYPES = [
    // ... existing entries ...
    PurchaseOrder::class => 'Purchase Order',
    GoodsReceipt::class  => 'Goods Receipt',
    Invoice::class       => 'Invoice',
];
```

---

## Factory Notes

### PurchaseOrderFactory
- `po_number` — use sequence: `PO-2026-` . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT)`
- `status` — default `PurchaseOrderStatus::Draft`
- `supplier_id` — `Supplier::factory()`
- `created_by` — `User::factory()`
- `subtotal`, `tax_total`, `grand_total` — use faker decimal values

### PurchaseOrderLineFactory
- `purchase_order_id` — `PurchaseOrder::factory()`
- `product_id` — `Product::factory()` (stub if not yet built)
- `qty_ordered` — random 1–100
- `qty_received` — default 0
- `qty_on_hand_snapshot` — `fake()->randomFloat(2, 0, 500)`
- `unit_cost` — random 10–1000
- `tax_rate` — default 0

### GoodsReceiptFactory
- `grn_number` — `GRN-2026-` + padded sequence
- `status` — default `GoodsReceiptStatus::Draft`
- `received_date` — `now()->toDateString()`

### InvoiceFactory
- `status` — default `InvoiceStatus::Pending`
- `invoice_date` — `now()->toDateString()`
- `due_date` — `now()->addDays(30)->toDateString()`
