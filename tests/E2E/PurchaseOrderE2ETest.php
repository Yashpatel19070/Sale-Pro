<?php

declare(strict_types=1);

/**
 * Purchase Order E2E Test Suite
 *
 * Covers end-to-end journeys for the procurement module:
 *   PO-01 to PO-10  — Purchase Order lifecycle
 *   GRN-01 to GRN-05 — Goods Receipt lifecycle
 *   INV-01 to INV-05 — Invoice lifecycle
 *
 * Feature tests already cover: auth redirects, forbidden responses,
 * and field-level validation. These tests focus on happy-path state
 * transitions and cross-resource side effects.
 */

use App\Enums\GoodsReceiptStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────────────────
// SETUP HELPERS
// ──────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->sales = User::factory()->create()->assignRole('sales');
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

// Shared helper: valid PO store payload with one line
function poPayload(int $supplierId, int $productId): array
{
    return [
        'supplier_id' => $supplierId,
        'expected_delivery_date' => now()->addDays(14)->toDateString(),
        'notes' => null,
        'lines' => [[
            'product_id' => $productId,
            'description' => 'E2E Widget',
            'qty_ordered' => 10,
            'unit_cost' => 25.00,
            'tax_rate' => 10,
        ]],
    ];
}

// Shared helper: valid GRN store payload
function grnPayload(int $poLineId, int|float|string $qty = 10): array
{
    return [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLineId,
            'qty_received' => $qty,
            'notes' => null,
        ]],
    ];
}

// Shared helper: valid invoice store payload
function invoicePayload(): array
{
    return [
        'invoice_number' => 'INV-E2E-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'amount' => 275.00,
        'notes' => null,
    ];
}

// ==============================================================================
// PO LIFECYCLE
// ==============================================================================

// ── PO-01: Admin creates PO → draft, visible on index ────────────────────────

it('[PO-01] admin creates a PO and it appears on the index as draft', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id));

    $po = PurchaseOrder::latest()->first();

    $response->assertRedirect(route('purchase-orders.show', $po));
    expect($po->status)->toBe(PurchaseOrderStatus::Draft);
    expect($po->lines()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index'))
        ->assertSee($po->po_number);
});

// ── PO-02: Admin edits draft PO ──────────────────────────────────────────────

it('[PO-02] admin edits a draft PO — supplier and notes are updated', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $newSupplier = Supplier::factory()->create();
    $updatePayload = [
        'supplier_id' => $newSupplier->id,
        'expected_delivery_date' => null,
        'notes' => 'Updated via E2E test',
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Updated Widget',
            'qty_ordered' => 8,
            'unit_cost' => 30.00,
            'tax_rate' => 0,
            'qty_on_hand_snapshot' => 0,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.update', $po), $updatePayload)
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'supplier_id' => $newSupplier->id,
        'notes' => 'Updated via E2E test',
    ]);
});

// ── PO-03: Admin submits PO → status becomes pending_approval ─────────────────

it('[PO-03] admin submits a draft PO — status transitions to pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

// ── PO-04: Admin approves PO → status becomes approved ───────────────────────

it('[PO-04] admin approves a pending PO — status transitions to approved, approved_by set', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Approved->value,
        'approved_by' => $this->admin->id,
    ]);

    expect(PurchaseOrder::find($po->id)->approved_at)->not->toBeNull();
});

// ── PO-05: Admin rejects PO → status becomes rejected, rejection_reason set ──

it('[PO-05] admin rejects a pending PO — status is rejected and rejection_reason persisted', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.reject', $po), ['rejection_reason' => 'Over budget'])
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Rejected->value,
        'rejection_reason' => 'Over budget',
    ]);
});

// ── PO-06: Admin marks approved PO as on-the-way ─────────────────────────────

it('[PO-06] admin marks an approved PO as on-the-way — status transitions to on_the_way', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.markOnTheWay', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::OnTheWay->value,
    ]);
});

// ── PO-07: Admin cancels PO ───────────────────────────────────────────────────

it('[PO-07] admin cancels an approved PO — status transitions to cancelled', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Cancelled->value,
    ]);
});

// ── PO-08: Admin soft-deletes PO, then restores it ───────────────────────────

it('[PO-08] admin soft-deletes then restores a PO — it is active again', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    // Soft delete
    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.destroy', $po))
        ->assertRedirect();

    $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);

    // Restore
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.restore', $po->id))
        ->assertRedirect();

    $this->assertNotSoftDeleted('purchase_orders', ['id' => $po->id]);
});

// ── PO-09: Sales user cannot create/delete PO ────────────────────────────────

it('[PO-09] sales user is forbidden from creating or deleting a PO', function () {
    // Cannot access create form
    $this->actingAs($this->sales)
        ->get(route('purchase-orders.create'))
        ->assertForbidden();

    // Cannot POST to store
    $this->actingAs($this->sales)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id))
        ->assertForbidden();

    // Cannot delete
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->sales)
        ->delete(route('purchase-orders.destroy', $po))
        ->assertForbidden();

    $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
});

// ── PO-10: Admin views print page ────────────────────────────────────────────

it('[PO-10] admin can view the print page for a PO', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.print', $po))
        ->assertOk()
        ->assertSee($po->po_number);
});

// ==============================================================================
// GRN LIFECYCLE
// ==============================================================================

// ── GRN-01: Admin creates GRN for approved PO → draft status ─────────────────

it('[GRN-01] admin creates a GRN for an approved PO — GRN is draft, PO qty unchanged', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), grnPayload($poLine->id, 5));

    $grn = GoodsReceipt::latest()->first();
    $response->assertRedirect(route('purchase-orders.goods-receipts.show', [$po, $grn]));

    $this->assertDatabaseHas('goods_receipts', [
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft->value,
    ]);

    // PO qty_received must NOT change until GRN is completed
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => 0,
    ]);
});

// ── GRN-02: Admin updates draft GRN ──────────────────────────────────────────

it('[GRN-02] admin updates a draft GRN — notes are persisted', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $updateData = [
        'received_date' => now()->toDateString(),
        'notes' => 'Packaging slightly damaged',
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 7,
            'notes' => null,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.goods-receipts.update', [$po, $grn]), $updateData)
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'notes' => 'Packaging slightly damaged',
    ]);
});

// ── GRN-03: Admin completes GRN → PO status updates ─────────────────────────

it('[GRN-03] completing a full GRN sets GRN to complete and PO to received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 10, // full quantity
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'status' => GoodsReceiptStatus::Complete->value,
    ]);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => 10,
    ]);
});

it('[GRN-03b] completing a partial GRN sets PO to partially_received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 4, // partial
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PartiallyReceived->value,
    ]);
});

// ── GRN-04: Cannot create GRN for draft PO ───────────────────────────────────

it('[GRN-04] admin cannot create a GRN for a draft PO — request is rejected with errors', function () {
    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(
            route('purchase-orders.goods-receipts.store', $draftPo),
            grnPayload($poLine->id, 5)
        )
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('goods_receipts', [
        'purchase_order_id' => $draftPo->id,
    ]);
});

// ── GRN-05: Cannot receive more qty than remaining ────────────────────────────

it('[GRN-05] admin cannot receive more qty than ordered — request is rejected with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(
            route('purchase-orders.goods-receipts.store', $po),
            grnPayload($poLine->id, 999) // exceeds qty_ordered
        )
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('goods_receipts', ['purchase_order_id' => $po->id]);
});

// ==============================================================================
// INVOICE LIFECYCLE
// ==============================================================================

// ── INV-01: Admin creates invoice for received PO → pending ──────────────────

it('[INV-01] admin creates an invoice for a received PO — status is pending, PO moves to invoiced', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $payload = invoicePayload();

    $response = $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), $payload);

    $invoice = Invoice::latest()->first();
    $response->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'purchase_order_id' => $po->id,
        'invoice_number' => $payload['invoice_number'],
        'status' => InvoiceStatus::Pending->value,
    ]);

    // Creating an invoice on a received PO should transition PO to invoiced
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);
});

// ── INV-02: Admin approves invoice → approved ─────────────────────────────────

it('[INV-02] admin approves a pending invoice — status transitions to approved, approved_by set', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Approved->value,
        'approved_by' => $this->admin->id,
    ]);

    expect(Invoice::find($invoice->id)->approved_at)->not->toBeNull();
});

// ── INV-03: Admin marks invoice as paid → paid, paid_at set ──────────────────

it('[INV-03] admin marks an approved invoice as paid — status is paid and paid_at is set', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Paid->value,
    ]);

    expect(Invoice::find($invoice->id)->paid_at)->not->toBeNull();
});

// ── INV-04: Marking all invoices paid closes PO ──────────────────────────────

it('[INV-04] when all invoices are paid the PO transitions to closed', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);

    // Single invoice — paying it should close the PO
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

it('[INV-04b] PO does NOT close when other invoices are still unpaid', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);

    // Two invoices — only one gets paid
    $invoice1 = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Approved,
    ]);
    Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending, // still pending
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice1]))
        ->assertRedirect();

    // PO must NOT be closed while the second invoice is still pending
    $this->assertDatabaseMissing('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

// ── INV-05: Cannot delete paid invoice ───────────────────────────────────────

it('[INV-05] admin cannot delete a paid invoice — request is rejected with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Closed,
    ]);
    $paidInvoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.invoices.destroy', [$po, $paidInvoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();

    // Invoice must NOT be deleted
    $this->assertDatabaseHas('invoices', ['id' => $paidInvoice->id, 'deleted_at' => null]);
});

// ==============================================================================
// CROSS-MODULE JOURNEYS
// ==============================================================================

// ── Journey-PO: Full PO lifecycle: draft → submit → approve → on-the-way ────

it('[Journey-PO] full PO state machine: draft → pending_approval → approved → on_the_way → cancelled', function () {
    // Step 1: Create draft PO via HTTP
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id))
        ->assertRedirect();

    $po = PurchaseOrder::latest()->first();
    expect($po->status)->toBe(PurchaseOrderStatus::Draft);

    // Step 2: Submit draft → pending_approval
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);

    // Step 3: Approve → approved
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $refreshed = $po->fresh();
    expect($refreshed->status)->toBe(PurchaseOrderStatus::Approved);
    expect($refreshed->approved_by)->toBe($this->admin->id);

    // Step 4: Mark on-the-way → on_the_way
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.markOnTheWay', $po))
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::OnTheWay);

    // Step 5: Cannot edit an on-the-way PO
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.edit', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Journey-GRN: Full procurement flow ───────────────────────────────────────

it('[Journey-GRN] full procurement: create PO → approve → create GRN → complete → PO received', function () {
    // Step 1: Create a PO (draft)
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id))
        ->assertRedirect();

    $po = PurchaseOrder::with('lines')->latest()->first();
    $poLine = $po->lines->first();

    // Step 2: Submit and approve the PO
    $this->actingAs($this->admin)->post(route('purchase-orders.submit', $po));
    $this->actingAs($this->admin)->post(route('purchase-orders.approve', $po));
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Approved);

    // Step 3: Create a GRN for the approved PO
    $this->actingAs($this->admin)
        ->post(
            route('purchase-orders.goods-receipts.store', $po),
            grnPayload($poLine->id, $poLine->qty_ordered)
        )
        ->assertRedirect();

    $grn = GoodsReceipt::where('purchase_order_id', $po->id)->latest()->first();
    expect($grn->status)->toBe(GoodsReceiptStatus::Draft);

    // GRN lines were stored
    expect($grn->lines()->count())->toBe(1);

    // PO qty_received must still be 0 (GRN is draft)
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => 0,
    ]);

    // Step 4: Complete the GRN
    // We need a GRN line — the store created one, so just complete
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    expect($grn->fresh()->status)->toBe(GoodsReceiptStatus::Complete);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Received);

    // qty_received on PO line updated
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => $poLine->qty_ordered,
    ]);
});

// ── Journey-INV: Invoice lifecycle → PO closed ───────────────────────────────

it('[Journey-INV] full invoice lifecycle: create → approve → mark paid → PO closed', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    // Step 1: Create invoice — PO transitions to invoiced
    $payload = invoicePayload();
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), $payload)
        ->assertRedirect();

    $invoice = Invoice::where('purchase_order_id', $po->id)->latest()->first();
    expect($invoice->status)->toBe(InvoiceStatus::Pending);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Invoiced);

    // Step 2: Approve invoice
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Approved);

    // Step 3: Mark as paid — PO should close (only one invoice)
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->fresh()->paid_at)->not->toBeNull();
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Closed);

    // Step 4: Trying to delete a paid invoice is rejected
    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.invoices.destroy', [$po, $invoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
});

// ── Journey-SALES: Sales user view-only boundary ─────────────────────────────

it('[Journey-SALES] sales user can view PO/GRN/Invoice pages but is blocked from write actions', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    // Read access — all OK
    $this->actingAs($this->sales)
        ->get(route('purchase-orders.index'))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.show', $po))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.invoices.show', [$po, $invoice]))
        ->assertOk();

    // Write access — all forbidden
    $this->actingAs($this->sales)
        ->post(route('purchase-orders.submit', $po))
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.approve', $po))
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.goods-receipts.store', $po), grnPayload(99, 1))
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertForbidden();
});
