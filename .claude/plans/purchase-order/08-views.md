# Purchase Order Module — Views

All views use `<x-app-layout>` (not `@extends('layouts.admin')`). Follow existing module patterns (customers, suppliers). Use Tailwind CSS v3.

---

## View: `purchase-orders/index.blade.php`

### Elements
- Page title: "Purchase Orders"
- "New Purchase Order" button (shown with `@can('create', App\Models\PurchaseOrder::class)`)
- Filter bar:
  - Text search (po_number)
  - Status dropdown (all PurchaseOrderStatus cases)
  - Supplier dropdown
  - Date from / Date to
  - "Filter" submit + "Clear" reset link
- Table columns:
  - PO Number (link to show)
  - Supplier name
  - Status badge (color from `$po->status->color()`)
  - Grand Total
  - Expected Delivery
  - Created By
  - Created At
  - Actions: View, Edit (if draft/rejected), Delete / Restore (if soft-deleted)
- **Soft-deleted rows**: render with `opacity-50` and strikethrough on the PO number; show a "Deleted" badge instead of status badge; replace actions with a "Restore" button for admin
- Pagination links
- Empty state message when no results
- Flash success/error alert at top

---

## View: `purchase-orders/show.blade.php`

### Sections

#### Header
- PO number, status badge, created date, created by
- Action buttons based on status + permissions:
  - `draft` → Edit, Submit, Cancel
  - `pending_approval` → Approve, Reject (if can approve)
  - `rejected` → Edit, Resubmit, Cancel
  - `approved` → Mark On The Way, Cancel, + "Receive Goods" button
  - `on_the_way` → "Receive Goods" button, Cancel
  - `partially_received` → "Receive Goods" button
  - `received` → "Add Invoice" button
  - `invoiced` → (no major actions)
  - `closed` → (read-only)
  - `cancelled` → (read-only)
- Rejection reason alert box (shown if status = `rejected`)
- Approval info (approved by, approved at) shown if approved

#### Supplier Info
- Name, contact, email, phone, payment terms

#### PO Details
- Expected delivery date, notes

#### Line Items Table
| Column |
|--------|
| Product |
| Description |
| Stock @ Order |
| Qty Ordered |
| Qty Received |
| Remaining |
| Unit Cost |
| Tax Rate |
| Line Total |

- Subtotal, Tax Total, Grand Total footer row

#### Goods Receipts Section
- Table: GRN number, received date, received by, status badge, actions (View, Edit if draft, Complete if draft, Delete if draft)
- Empty state if no GRNs yet
- "Record Goods Receipt" button (if allowed by status)

#### Invoices Section
- Table: Invoice number, date, due date, amount, status, actions (View, Approve, Mark Paid, Delete)
- Empty state if no invoices
- "Add Invoice" button (if allowed by status)

#### Audit Log Section
- List of activity log entries for this PO (description, user, date)

---

## View: `purchase-orders/create.blade.php`

### Elements
- Supplier select (active suppliers only)
- Expected delivery date picker
- Notes textarea
- **Line Items Builder** (Alpine.js):
  - Dynamic add/remove rows
  - Per row: product select, description (auto-filled from product), qty, unit cost, tax rate %, line total (computed)
  - Minimum 1 line enforced
  - Running subtotal, tax total, grand total at bottom
- Submit button
- Cancel link back to index

### Alpine.js Line Builder
- `x-data` stores array of lines
- Each line: `{ product_id, description, qty_ordered, unit_cost, tax_rate, line_total }`
- `line_total` computed: `(qty × unit_cost) + (qty × unit_cost × tax_rate / 100)`
- Product select `@change` → auto-fill description from product name
- "Add Line" button appends new empty row
- "Remove" button on each row (disabled if only 1 line)
- Grand total computed from all line totals

---

## View: `purchase-orders/edit.blade.php`

- Same structure as create
- Pre-fills all header fields with `old('field', $purchaseOrder->field)`
- Pre-fills lines from `$purchaseOrder->lines` (passed as JSON to Alpine)
- Shows rejection reason alert if `$purchaseOrder->status = rejected`

### qty_on_hand_snapshot preservation (REQUIRED)
Each line row in the Alpine template must include a hidden input to carry the snapshot through the form submit unchanged:
```html
<input type="hidden" :name="`lines[${index}][qty_on_hand_snapshot]`" :value="line.qty_on_hand_snapshot">
```
Service `update()` never re-queries inventory — it reads this value from the request. If input is missing, snapshot becomes `null` on update.

---

## View: `goods-receipts/create.blade.php`

### Elements
- PO summary header (PO number, supplier, status)
- Received date picker (default today)
- Notes textarea
- **Line Items** — one row per PO line:
  - Product name (read-only)
  - Qty Ordered (read-only)
  - Already Received (read-only)
  - Remaining (read-only, computed)
  - **Qty to Receive** (input, max = remaining, 0 = skip this line)
  - Notes per line (optional)
- Lines with zero remaining are shown greyed out / disabled
- Submit button

---

## View: `goods-receipts/edit.blade.php`

### Elements
- Same structure as create, pre-filled from `$goodsReceipt->lines`
- Only accessible when status = `draft`
- Shows GRN number and current received date pre-filled

---

## View: `goods-receipts/show.blade.php`

### Elements
- GRN number, received date, received by, status badge
- Link back to PO
- Lines table: product, ordered qty, received qty in this GRN, notes
- Action buttons (shown when status = `draft` and user has permission):
  - Edit button → `goods-receipts.edit`
  - Complete button → `goods-receipts.complete` (POST form)
  - Delete button → `goods-receipts.destroy` (DELETE form)

---

## View: `invoices/create.blade.php`

### Elements
- PO summary header
- Invoice number input
- Invoice date picker
- Due date picker (optional)
- Amount input
- Notes textarea
- Submit button

---

## View: `invoices/show.blade.php`

### Elements
- Invoice number, date, due date
- Status badge
- Amount
- PO link
- Approve button (if status = pending and user can approve)
- Mark Paid button (if status = approved and user can markPaid)
- Delete button (if not paid and user can delete)
- Approval info (approved by, at)
- Paid at timestamp

---

## View: `purchase-orders/print.blade.php`

### Elements
- No `layouts.admin` — standalone HTML file with `<html>/<head>/<body>`
- Print CSS: `<style>@media print { ... }</style>` + Tailwind CDN for screen preview
- Company / PO header: PO number, date, supplier name + address
- Status badge
- Line items table: product, description, qty ordered, unit cost, tax rate, line total
- Stock @ order time column (`qty_on_hand_snapshot`) — informational
- Subtotal, tax total, grand total footer
- Notes section (if present)
- "Print / Save as PDF" button — calls `window.print()`, hidden on print via CSS
- Auto-triggers `window.print()` on page load via `<script>window.onload = () => window.print();</script>` (can be removed if user prefers manual)

### File Map Addition
| File | Path |
|------|------|
| print | `resources/views/purchase-orders/print.blade.php` |

---

## Shared UI Patterns
- Status badges: `<span class="px-2 py-1 text-xs rounded-full bg-{color}-100 text-{color}-700">{{ $status->label() }}</span>`
- Flash alerts: include existing `@include('partials.flash')` component
- Confirm dialog on delete: `onclick="return confirm('Are you sure?')"`
- All action forms use `@csrf` + `@method('DELETE'/'PUT'/'PATCH')` as needed
- `@can` gates wrap all action buttons
