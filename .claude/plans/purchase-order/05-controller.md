# Purchase Order Module — Controllers

---

## Controller: `PurchaseOrderController`

**File:** `app/Http/Controllers/PurchaseOrderController.php`

### Actions

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET /purchase-orders | Paginated list with filters |
| `create` | GET /purchase-orders/create | Create form |
| `store` | POST /purchase-orders | Save new PO |
| `show` | GET /purchase-orders/{purchaseOrder} | PO detail + lines + GRNs + invoices |
| `edit` | GET /purchase-orders/{purchaseOrder}/edit | Edit form (draft/rejected only) |
| `update` | PUT /purchase-orders/{purchaseOrder} | Save edits |
| `destroy` | DELETE /purchase-orders/{purchaseOrder} | Soft delete |
| `restore` | POST /purchase-orders/{purchaseOrder}/restore | Restore deleted PO |
| `submit` | POST /purchase-orders/{purchaseOrder}/submit | Submit for approval |
| `approve` | POST /purchase-orders/{purchaseOrder}/approve | Approve PO |
| `reject` | POST /purchase-orders/{purchaseOrder}/reject | Reject PO with reason |
| `markOnTheWay` | POST /purchase-orders/{purchaseOrder}/on-the-way | Mark as shipped |
| `qualityCheck` | POST /purchase-orders/{purchaseOrder}/quality-check | Pass QC, set PO to received |
| `cancel` | POST /purchase-orders/{purchaseOrder}/cancel | Cancel PO |
| `print` | GET /purchase-orders/{purchaseOrder}/print | Print-friendly HTML view |

### index
- `$this->authorize('viewAny', PurchaseOrder::class)`
- Calls `$this->service->paginate($request->only(['search', 'status', 'supplier_id', 'date_from', 'date_to']))`
- Loads suppliers list for filter dropdown
- Returns view `purchase-orders.index` with `$pos`, `$suppliers`, `$filters`

### create
- `$this->authorize('create', PurchaseOrder::class)`
- Loads active suppliers and products for select inputs
- Returns view `purchase-orders.create` with `$suppliers`, `$products`

### store
- `$this->authorize('create', PurchaseOrder::class)`
- Receives `StorePurchaseOrderRequest`
- Calls `$this->service->store($request->validated(), auth()->user())`
- Redirects to `purchase-orders.show` with `with('success', 'Purchase order created.')`

### show
- `$this->authorize('view', $purchaseOrder)`
- Eager loads: `$purchaseOrder->load(['supplier', 'lines.product', 'goodsReceipts.lines.purchaseOrderLine', 'goodsReceipts.receivedBy', 'invoices.approvedBy', 'createdBy', 'approvedBy'])`
- Computes `$assignedGrnIds` — set of GRN IDs that already have inventory movements:
  ```php
  $grnIds = $purchaseOrder->goodsReceipts->pluck('id')->all();
  $assignedGrnIds = $grnIds
      ? InventoryMovement::whereIn('goods_receipt_id', $grnIds)
          ->select('goods_receipt_id')->distinct()
          ->pluck('goods_receipt_id')->flip()->all()
      : [];
  ```
  Used in view to show per-GRN badge: "Serials Assigned" vs "Serials Pending"
- Returns view `purchase-orders.show` with `$purchaseOrder`, `$assignedGrnIds`

### edit
- `$this->authorize('update', $purchaseOrder)`
- Guard: redirect back with error if status not `draft` or `rejected`
- Loads suppliers and products
- Returns view `purchase-orders.edit`

### update
- `$this->authorize('update', $purchaseOrder)`
- Receives `UpdatePurchaseOrderRequest`
- Catches `DomainException` → redirect back with `with('error', $e->getMessage())`
- On success: redirect to `purchase-orders.show`

### destroy
- `$this->authorize('delete', $purchaseOrder)`
- Calls `$this->service->delete($purchaseOrder)`
- Redirects to `purchase-orders.index`

### restore
- `$this->authorize('restore', $purchaseOrder)`
- Route binding must use `->withTrashed()`
- Calls `$this->service->restore($purchaseOrder)`
- Redirects to `purchase-orders.show`

### submit
- `$this->authorize('submit', $purchaseOrder)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### approve
- `$this->authorize('approve', $purchaseOrder)`
- Calls `$this->service->approve($purchaseOrder, auth()->user())`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### reject
- `$this->authorize('reject', $purchaseOrder)`
- Receives `RejectPurchaseOrderRequest`
- Calls `$this->service->reject($purchaseOrder, $request->validated()['rejection_reason'])`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### markOnTheWay
- `$this->authorize('update', $purchaseOrder)`
- Calls `$this->service->markOnTheWay($purchaseOrder)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### qualityCheck
- `$this->authorize('qualityCheck', $purchaseOrder)`
- Receives plain `Request` — only needs `qc_notes` (nullable string, no FormRequest needed)
- Calls `$this->service->passQualityCheck($purchaseOrder, $request->input('qc_notes'))`
- Catches `DomainException`
- Redirects to `purchase-orders.show` with success

### cancel
- `$this->authorize('cancel', $purchaseOrder)`
- Calls `$this->service->cancel($purchaseOrder)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### print
- `$this->authorize('view', $purchaseOrder)`
- Eager loads: `$purchaseOrder->load(['supplier', 'lines.product', 'createdBy', 'approvedBy'])`
- Returns view `purchase-orders.print` — no layout wrapper, print CSS only
- No service call needed

---

## Controller: `GoodsReceiptController`

**File:** `app/Http/Controllers/GoodsReceiptController.php`

### Actions

| Method | Route | Description |
|--------|-------|-------------|
| `create` | GET /purchase-orders/{purchaseOrder}/goods-receipts/create | GRN form |
| `store` | POST /purchase-orders/{purchaseOrder}/goods-receipts | Save draft GRN |
| `show` | GET /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt} | GRN detail |
| `edit` | GET /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt}/edit | Edit draft GRN |
| `update` | PUT /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt} | Save edits |
| `complete` | POST /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt}/complete | Complete GRN |
| `submitQc` | POST /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt}/qc | Submit QC results per line |
| `assignSerials` | GET /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt}/assign-serials | Serial assignment form (QC flow) |
| `storeSerials` | POST /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt}/assign-serials | Generate serials — goods_receipt_id guaranteed |
| `destroy` | DELETE /purchase-orders/{purchaseOrder}/goods-receipts/{goodsReceipt} | Soft delete draft GRN |

### create
- `$this->authorize('create', GoodsReceipt::class)`
- Loads `$purchaseOrder` with lines and product info
- Returns view `goods-receipts.create` with `$purchaseOrder`

### store
- `$this->authorize('create', GoodsReceipt::class)`
- Receives `StoreGoodsReceiptRequest`
- Calls `$this->service->store($purchaseOrder, $request->validated(), auth()->user())`
- Catches `DomainException`
- On success: `redirect()->route('goods-receipts.show', [$purchaseOrder, $goodsReceipt])` — both route params required (GRN route is nested under PO)

### show
- `$this->authorize('view', $goodsReceipt)`
- Loads: `$goodsReceipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine.product', 'lines.qcInspectedBy', 'receivedBy'])` — `lines.qcInspectedBy` required to avoid N+1 when QC results table renders inspector names (BUG-005)
- Computes `$serialsAssigned = InventoryMovement::where('goods_receipt_id', $goodsReceipt->id)->exists()` — passed to view to toggle "Assign Serials" button vs "Serials Assigned ✓" badge
- Returns view `goods-receipts.show` with `$purchaseOrder`, `$goodsReceipt`, `$serialsAssigned`

### edit
- `$this->authorize('update', $goodsReceipt)`
- Guard: redirect back with error if status = `complete`
- Loads `$purchaseOrder->load(['supplier', 'lines.product'])` — `supplier` required because view renders `$goodsReceipt->purchaseOrder->supplier->name` (BUG-004)
- Returns view `goods-receipts.edit`

### update
- `$this->authorize('update', $goodsReceipt)`
- Receives `StoreGoodsReceiptRequest` (same validation)
- Calls `$this->service->update($goodsReceipt, $request->validated())`
- Catches `DomainException`
- On success: redirect to `goods-receipts.show`

### complete
- `$this->authorize('update', $goodsReceipt)`
- Calls `$this->service->complete($goodsReceipt)`
- Catches `DomainException`
- On success: `redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Goods receipt completed.')` — `$purchaseOrder` is a route param, must be passed

### submitQc
- `$this->authorize('update', $goodsReceipt)`
- Receives `StoreGoodsReceiptQcRequest`
- Calls `$this->service->submitQc($goodsReceipt, $request->validated(), $request->user())`
- Catches `DomainException` → redirect back with `withErrors(['error' => $e->getMessage()])`
- On success: `redirect()->route('purchase-orders.goods-receipts.show', [$purchaseOrder, $goodsReceipt])->with('success', 'QC submitted. Assign serials below.')`
- Redirects to GRN show (not PO show) so user lands directly on serial assignment section

### assignSerials
- `$this->authorize('bulkReceive', \App\Models\InventoryMovement::class)`
- Guard: redirect back if GRN status not `complete` OR any line `qty_passed IS NULL` (QC not done)
- Guard: redirect back if PO status not in `[partially_received, received]`
- Guard: redirect to GRN show if `InventoryMovement::where('goods_receipt_id', $goodsReceipt->id)->exists()` — serials already assigned, form must not render again
- Loads: `$goodsReceipt->load(['lines.purchaseOrderLine.product', 'receivedBy'])` — `receivedBy` required by view (BUG-006)
- Loads: `$purchaseOrder->loadMissing('supplier')` — `supplier->name` rendered in view header (BUG-006 / BUG-010)
- Loads: `$locations = $this->locationService->activeForDropdown()`
- Returns view `goods-receipts.assign-serials` with `$purchaseOrder`, `$goodsReceipt`, `$locations`
- **No products dropdown needed** — product is fixed from GRN line, not user-selectable

### storeSerials
- `$this->authorize('bulkReceive', \App\Models\InventoryMovement::class)`
- Receives `StoreGrnSerialRequest`
- Calls `$this->movementService->bulkReceiveFromGrn($goodsReceipt, $request->validated(), $request->user())`
  - `goods_receipt_id = $goodsReceipt->id` — set inside service, never from request
- Catches `DomainException` → redirect back with error
- On success:
  - Stores serial IDs in session key `bulk_receive_ids` (this key is what the print controller reads)
  - `redirect()->route('inventory-movements.bulk-receive-print')->with('success', "Generated {$count} serial numbers.")`
- **Do NOT redirect back to `assignSerials`** — the service-level guard would immediately block with "already assigned" error

### destroy
- `$this->authorize('delete', $goodsReceipt)`
- Catches `DomainException`
- `redirect()->route('purchase-orders.show', $purchaseOrder)` — `$purchaseOrder` is a route param, must be passed

---

## Controller: `InvoiceController`

**File:** `app/Http/Controllers/InvoiceController.php`

### Actions

| Method | Route | Description |
|--------|-------|-------------|
| `create` | GET /purchase-orders/{purchaseOrder}/invoices/create | Invoice form |
| `store` | POST /purchase-orders/{purchaseOrder}/invoices | Save invoice |
| `show` | GET /purchase-orders/{purchaseOrder}/invoices/{invoice} | Invoice detail |
| `approve` | POST /purchase-orders/{purchaseOrder}/invoices/{invoice}/approve | Approve invoice |
| `markPaid` | POST /purchase-orders/{purchaseOrder}/invoices/{invoice}/mark-paid | Mark paid |
| `destroy` | DELETE /purchase-orders/{purchaseOrder}/invoices/{invoice} | Soft delete |

### create
- `$this->authorize('create', Invoice::class)`
- Returns view `invoices.create` with `$purchaseOrder`

### store
- `$this->authorize('create', Invoice::class)`
- Receives `StoreInvoiceRequest`
- Catches `DomainException`
- On success: redirect to `purchase-orders.show`

### show
- `$this->authorize('view', $invoice)`
- Loads: `$invoice->load(['purchaseOrder.supplier', 'approvedBy'])`
- Returns view `invoices.show`

### approve
- `$this->authorize('approve', $invoice)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### markPaid
- `$this->authorize('markPaid', $invoice)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

### destroy
- `$this->authorize('delete', $invoice)`
- Catches `DomainException`
- Redirects to `purchase-orders.show`

---

## Rules
- Every action calls `$this->authorize()` — no exceptions
- Typed FormRequest injection — never plain `Request` for write actions (exception: `qualityCheck` only needs nullable `qc_notes` string — plain `Request` acceptable)
- All `DomainException` caught at controller level — never bubble to user as 500
- All redirects use named routes
- Flash messages: `with('success', '...')` or `with('error', '...')`
- Constructor injects service via DI — `readonly` on all injected services:
  ```php
  // PurchaseOrderController
  public function __construct(private readonly PurchaseOrderService $service) {}

  // GoodsReceiptController — needs both services (serial generation is inventory concern)
  public function __construct(
      private readonly GoodsReceiptService $service,
      private readonly InventoryMovementService $movementService,
  ) {}

  // InvoiceController
  public function __construct(private readonly InvoiceService $service) {}
  ```
- `$purchaseOrder` and `$goodsReceipt` route parameters are auto-resolved via Laravel implicit model binding — do NOT add manual `PurchaseOrder::findOrFail()` lookups
- GRN `update` action reuses `StoreGoodsReceiptRequest` (same validation as store) — there is no separate `UpdateGoodsReceiptRequest` class; this is intentional
- `storeSerials` calls `$this->movementService->bulkReceiveFromGrn()` — serial generation is inventory concern, lives in `InventoryMovementService`, not `GoodsReceiptService`
