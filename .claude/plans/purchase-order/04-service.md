# Purchase Order Module — Services

---

## Service: `PurchaseOrderService`

**File:** `app/Services/PurchaseOrderService.php`

### Methods

#### `paginate(array $filters): LengthAwarePaginator`
- Filters: `search` (po_number), `status`, `supplier_id`, `date_from`, `date_to`
- Includes soft-deleted via `withTrashed()`
- Eager loads: `with(['supplier', 'createdBy', 'approvedBy'])`
- Order: `latest()`
- 20 per page, `withQueryString()`

#### `store(array $data, User $createdBy): PurchaseOrder`
- Wraps in `DB::transaction()`
- Generates `po_number` via `generatePoNumber()`
- Sets `created_by = $createdBy->id`, `status = PurchaseOrderStatus::Draft`
- Creates `PurchaseOrder` record
- Creates `PurchaseOrderLine` records from `$data['lines']`
- For each line: set `qty_on_hand_snapshot` via `InventorySerial::where('product_id', $line['product_id'])->where('status', SerialStatus::InStock)->count()`
- Recalculates and saves `subtotal`, `tax_total`, `grand_total`
- Returns fresh PO with lines eager loaded

#### `update(PurchaseOrder $po, array $data): PurchaseOrder`
- Guards: throw `DomainException` if status not `draft` or `rejected`
- Wraps in `DB::transaction()`
- Updates PO header
- Deletes existing lines, recreates from `$data['lines']`
- Each line must carry `qty_on_hand_snapshot` from the original — do NOT re-query inventory; value passed through from request
- Recalculates totals
- Returns `$po->fresh()->load(['lines.product', 'supplier'])`
- **Double guard note:** Controller `edit` action also redirects back when status is not `draft`/`rejected` (UX guard to avoid loading the form). Service guard is the authoritative enforcement — the service guard must never be removed even if the controller guard exists.

#### `submit(PurchaseOrder $po): PurchaseOrder`
- Guards: throw `DomainException` if status not `draft` or `rejected`
- Updates status → `pending_approval`
- Clears `rejection_reason`
- Returns fresh PO

#### `approve(PurchaseOrder $po, User $approver): PurchaseOrder`
- Guards: throw `DomainException` if status not `pending_approval`
- Updates status → `approved`, sets `approved_by`, `approved_at`
- Returns fresh PO

#### `reject(PurchaseOrder $po, string $reason): PurchaseOrder`
- Guards: throw `DomainException` if status not `pending_approval`
- Updates status → `rejected`, sets `rejection_reason`
- Clears `approved_by`, `approved_at`
- Returns fresh PO

#### `markOnTheWay(PurchaseOrder $po): PurchaseOrder`
- Guards: throw `DomainException` if status not `approved`
- Updates status → `on_the_way`
- Returns fresh PO
- **Strict guard** — throws even if already `on_the_way`. The controller/view must not show the "Mark On The Way" button for POs already in that state.

#### `cancel(PurchaseOrder $po): PurchaseOrder`
- Guards: throw `DomainException` if status is `closed`, `cancelled`, or `returned`
- Updates status → `cancelled`
- Returns fresh PO

#### `delete(PurchaseOrder $po): void`
- Soft delete
- No guard needed (cancelled POs can be deleted)

#### `restore(PurchaseOrder $po): void`
- Restore soft-deleted PO

#### `generatePoNumber(): string`
- Query max po_number for current year
- Increment sequence, zero-pad to 4 digits
- Return `PO-{YEAR}-{SEQUENCE}` e.g. `PO-2026-0042`
- **Race condition note:** `MAX()`-based generation is not atomic. Two concurrent requests can generate the same sequence. Wrap in `DB::transaction()` with a table-level lock, or accept the occasional unique constraint exception and retry. For low-volume PO creation this is acceptable; add optimistic retry if needed.

#### `recalculateTotals(PurchaseOrder $po): void`
- Internal helper
- Sums lines: `subtotal = sum(qty_ordered × unit_cost)`
- `tax_total = sum(qty_ordered × unit_cost × tax_rate / 100)`
- `grand_total = subtotal + tax_total`
- Updates PO record

---

## Service: `GoodsReceiptService`

**File:** `app/Services/GoodsReceiptService.php`

### Methods

#### `store(PurchaseOrder $po, array $data, User $receivedBy): GoodsReceipt`
- Guards: throw `DomainException` if PO status not in `[approved, on_the_way, partially_received]`
- Guards: throw `DomainException` if any line `qty_received` > `remaining_qty` on PO line
- Wraps in `DB::transaction()`
- Generates `grn_number` via `generateGrnNumber()`
- Sets `received_by`, `received_date`, `status = GoodsReceiptStatus::Draft`
- Creates `GoodsReceipt` record
- Creates `GoodsReceiptLine` records
- Does NOT update PO qty yet — committed only on `complete()`
- Returns fresh GRN with lines

#### `update(GoodsReceipt $grn, array $data): GoodsReceipt`
- Guards: throw `DomainException` if status is `complete`
- Guards: throw `DomainException` if any line `qty_received` > `remaining_qty` on PO line
- Wraps in `DB::transaction()`
- Updates GRN header fields
- Deletes existing lines, recreates from `$data['lines']`
- Returns fresh GRN with lines

#### `complete(GoodsReceipt $grn): GoodsReceipt`
- Guards: throw `DomainException` if status already `complete`
- Wraps in `DB::transaction()`
- Updates status → `complete`
- Calls `updatePoQtyReceived()` — recalculates `qty_received` on each PO line (completed GRNs only)
- Calls `updatePoStatus()` — recalculates PO status
- Returns `$grn->loadMissing(['lines.purchaseOrderLine.product', 'purchaseOrder.supplier', 'receivedBy'])`
  (use `loadMissing` not `fresh` — avoids re-running the base query on an already-loaded model)

#### `delete(GoodsReceipt $grn): void`
- Guards: throw `DomainException` if status is `complete`
- Soft delete GRN (no qty reversal needed — draft never committed qty)

#### `updatePoQtyReceived(PurchaseOrder $po): void`
- Internal helper
- Recalculates `qty_received` on each PO line by summing **only completed GRNs**
- SQL pattern (join filters out draft and soft-deleted GRNs):
  ```sql
  SELECT SUM(grl.qty_received)
  FROM   goods_receipt_lines grl
  JOIN   goods_receipts gr ON gr.id = grl.goods_receipt_id
  WHERE  grl.purchase_order_line_id = ?
    AND  gr.status = 'complete'
    AND  gr.deleted_at IS NULL
  ```
- Cap result at `qty_ordered` to guard against concurrent over-receipt edge cases

#### `updatePoStatus(PurchaseOrder $po): void`
- Internal helper
- Load all PO lines with `qty_ordered` and `qty_received`
- If all lines `qty_received >= qty_ordered` → PO status = `received`
- If some lines have `qty_received > 0` but not all full → PO status = `partially_received`
- Saves PO

#### `generateGrnNumber(): string`
- Same pattern as `generatePoNumber()` but uses `GRN-{YEAR}-{SEQUENCE}`
- Same race condition caveat applies — wrap in `DB::transaction()`

---

## Service: `InvoiceService`

**File:** `app/Services/InvoiceService.php`

### Methods

#### `store(PurchaseOrder $po, array $data): Invoice`
- Guards: throw `DomainException` if PO status not in `[approved, on_the_way, partially_received, received]`
- Creates `Invoice` record linked to PO
- Sets `status = InvoiceStatus::Pending`
- **Conditional PO status transition:** if PO status is `received`, update PO status → `invoiced` immediately (goods are received; invoice is now the active step)
- Returns fresh Invoice

#### `approve(Invoice $invoice, User $approver): Invoice`
- Guards: throw `DomainException` if status not `pending`
- Updates status → `approved`, sets `approved_by`, `approved_at`
- Returns fresh Invoice

#### `markPaid(Invoice $invoice): Invoice`
- Guards: throw `DomainException` if status not `approved`
- Updates status → `paid`, sets `paid_at = now()`
- Checks if all invoices on PO are paid → if yes, updates PO status → `closed`
- Returns fresh Invoice

#### `delete(Invoice $invoice): void`
- Guards: throw `DomainException` if status is `paid`
- Soft delete

---

## Rules Across All Services

- Every multi-table write wrapped in `DB::transaction()`
- Immutability: use `$model->fresh()` before update, return fresh copy
- Never call `$request->all()` — only receive pre-validated arrays
- All status guard failures throw `DomainException` with descriptive message
- Controllers catch `DomainException` and redirect back with `with('error', $e->getMessage())`
- `paginate()` always uses `withQueryString()`
- No manual activity logging — `LogsActivity` trait handles it
