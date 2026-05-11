# Purchase Order Module — Form Requests

---

## Request: `StorePurchaseOrderRequest`

**File:** `app/Http/Requests/PurchaseOrder/StorePurchaseOrderRequest.php`

### authorize()
```php
return $this->user()->can('purchase_orders.create');
```

### rules()
| Field | Rules |
|-------|-------|
| `supplier_id` | required, integer, exists:suppliers,id |
| `expected_delivery_date` | nullable, date, after_or_equal:today |
| `notes` | nullable, string, max:2000 |
| `lines` | required, array, min:1 |
| `lines.*.product_id` | required, integer, exists:products,id |
| `lines.*.description` | required, string, max:500 |
| `lines.*.qty_ordered` | required, numeric, min:0.01 |
| `lines.*.unit_cost` | required, numeric, min:0 |
| `lines.*.tax_rate` | required, numeric, min:0, max:100 |

---

## Request: `UpdatePurchaseOrderRequest`

**File:** `app/Http/Requests/PurchaseOrder/UpdatePurchaseOrderRequest.php`

### authorize()
```php
return $this->user()->can('purchase_orders.update');
```

### rules()
Same as `StorePurchaseOrderRequest` with one addition:

| Field | Rules |
|-------|-------|
| `supplier_id` | required, integer, exists:suppliers,id |
| `expected_delivery_date` | nullable, date, after_or_equal:today |
| `notes` | nullable, string, max:2000 |
| `lines` | required, array, min:1 |
| `lines.*.product_id` | required, integer, exists:products,id |
| `lines.*.description` | required, string, max:500 |
| `lines.*.qty_ordered` | required, numeric, min:0.01 |
| `lines.*.unit_cost` | required, numeric, min:0 |
| `lines.*.tax_rate` | required, numeric, min:0, max:100 |
| `lines.*.qty_on_hand_snapshot` | required, numeric, min:0 |

> `qty_on_hand_snapshot` is `required` on update — the edit form always has the frozen value from the loaded PO lines and must pass it through as a hidden input per line (`<input type="hidden" name="lines[i][qty_on_hand_snapshot]" value="{{ $line->qty_on_hand_snapshot }}">`). Making it required prevents silent null if JavaScript loses the field. Service must NOT re-query inventory on update — the snapshot frozen at `store()` is the source of truth for that PO line.

---

## Request: `RejectPurchaseOrderRequest`

**File:** `app/Http/Requests/PurchaseOrder/RejectPurchaseOrderRequest.php`

### authorize()
```php
return $this->user()->can('purchase_orders.reject');
```

### rules()
| Field | Rules |
|-------|-------|
| `rejection_reason` | required, string, min:10, max:1000 |

---

## Request: `StoreGoodsReceiptRequest`

**File:** `app/Http/Requests/GoodsReceipt/StoreGoodsReceiptRequest.php`

### authorize()
```php
return $this->user()->can('goods_receipts.create');
```

### rules()
| Field | Rules |
|-------|-------|
| `received_date` | required, date, before_or_equal:today |
| `notes` | nullable, string, max:2000 |
| `lines` | required, array, min:1 |
| `lines.*.purchase_order_line_id` | required, integer, exists:purchase_order_lines,id |
| `lines.*.qty_received` | required, numeric, min:0.01 |
| `lines.*.notes` | nullable, string, max:500 |

### prepareForValidation()
- Filter out lines where `qty_received` is 0 or null — only submit lines with actual qty

---

## Request: `StoreGoodsReceiptQcRequest`

**File:** `app/Http/Requests/GoodsReceipt/StoreGoodsReceiptQcRequest.php`

### authorize()
```php
return $this->user()->can(Permission::GOODS_RECEIPTS_UPDATE);
```

### rules()
| Field | Rules |
|-------|-------|
| `lines` | required, array, min:1 |
| `lines.*.goods_receipt_line_id` | required, integer, exists:goods_receipt_lines,id |
| `lines.*.qty_passed` | required, integer, min:0 |
| `lines.*.qty_failed` | required, integer, min:0 |

### after()
Cross-field rule — `qty_passed + qty_failed` must equal `qty_received` exactly. Uses `after()` per reference (not `withValidator()`):
```php
public function after(): array
{
    return [
        function ($validator) {
            foreach ($this->input('lines', []) as $i => $line) {
                $grnLine = \App\Models\GoodsReceiptLine::find($line['goods_receipt_line_id'] ?? null);
                if (! $grnLine) continue;
                $sum = (int) ($line['qty_passed'] ?? 0) + (int) ($line['qty_failed'] ?? 0);
                if ($sum !== (int) $grnLine->qty_received) {
                    $validator->errors()->add(
                        "lines.{$i}.qty_failed",
                        "Pass + fail ({$sum}) must equal received qty ({$grnLine->qty_received})."
                    );
                }
            }
        },
    ];
}
```

> **Agent note:** `after()` fires after standard rules pass. Service also enforces this invariant inside transaction — double guard, service is authoritative.

---

## Request: `StoreGrnSerialRequest`

**File:** `app/Http/Requests/GoodsReceipt/StoreGrnSerialRequest.php`

### authorize()
```php
return $this->user()->can(Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE);
```

### rules()
| Field | Rules |
|-------|-------|
| `lines` | required, array, min:1 |
| `lines.*.goods_receipt_line_id` | required, integer, exists:goods_receipt_lines,id |
| `lines.*.inventory_location_id` | required, integer, exists:inventory_locations,id |
| `lines.*.purchase_price` | required, numeric, min:0, max:999999.99 |

> **No `qty` field** — qty comes from `qty_passed` on the GRN line (set at QC time). Service reads it directly from the model; user cannot override it.
> **No `product_id` field** — product fixed from GRN line → `purchaseOrderLine.product_id`. Not user-supplied.
> **No `grn_id` field** — GRN is a route parameter (`{goodsReceipt}`), never from request body. Guaranteed non-null.

---

## Request: `StoreInvoiceRequest`

**File:** `app/Http/Requests/Invoice/StoreInvoiceRequest.php`

### authorize()
```php
return $this->user()->can('invoices.create');
```

### rules()
| Field | Rules |
|-------|-------|
| `invoice_number` | required, string, max:100 |
| `invoice_date` | required, date |
| `due_date` | nullable, date, after_or_equal:invoice_date |
| `amount` | required, numeric, min:0.01 |
| `notes` | nullable, string, max:2000 |

---

## Rules
- All requests use `$this->user()->can()` in `authorize()` — delegate to permission, not policy directly
- `$request->validated()` always — controllers never call `$request->all()`
- `lines.*` nested validation handles dynamic line item arrays
- `prepareForValidation()` in `StoreGoodsReceiptRequest` strips zero-qty lines before validation runs
