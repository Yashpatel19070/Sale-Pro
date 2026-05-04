<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->service = new InvoiceService;
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = User::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $this->invoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Pending,
    ]);
});

// ── store() ───────────────────────────────────────────────────────────────────

it('store() creates an invoice with status pending', function () {
    $data = [
        'invoice_number' => 'INV-001',
        'invoice_date' => now()->toDateString(),
        'due_date' => null,
        'amount' => 100.00,
        'notes' => null,
    ];

    $invoice = $this->service->store($this->po, $data);

    expect($invoice->status)->toBe(InvoiceStatus::Pending);
    $this->assertDatabaseHas('invoices', [
        'purchase_order_id' => $this->po->id,
        'invoice_number' => 'INV-001',
        'status' => InvoiceStatus::Pending->value,
    ]);
});

it('store() transitions PO to invoiced when PO status is received', function () {
    $data = [
        'invoice_number' => 'INV-002',
        'invoice_date' => now()->toDateString(),
        'due_date' => null,
        'amount' => 100.00,
        'notes' => null,
    ];

    $this->service->store($this->po, $data);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);
});

it('store() does NOT change PO status when PO is approved (not received)', function () {
    $approvedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $data = [
        'invoice_number' => 'INV-003',
        'invoice_date' => now()->toDateString(),
        'due_date' => null,
        'amount' => 100.00,
        'notes' => null,
    ];

    $this->service->store($approvedPo, $data);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $approvedPo->id,
        'status' => PurchaseOrderStatus::Approved->value,
    ]);
});

it('store() throws DomainException when PO status is draft', function () {
    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $data = [
        'invoice_number' => 'INV-004',
        'invoice_date' => now()->toDateString(),
        'due_date' => null,
        'amount' => 100.00,
        'notes' => null,
    ];

    expect(fn () => $this->service->store($draftPo, $data))
        ->toThrow(DomainException::class);
});

// ── approve() ─────────────────────────────────────────────────────────────────

it('approve() sets approved_by and approved_at', function () {
    $result = $this->service->approve($this->invoice, $this->user);

    expect($result->status)->toBe(InvoiceStatus::Approved);
    expect($result->approved_by)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
});

it('approve() throws DomainException when status is not pending', function () {
    $approvedInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    expect(fn () => $this->service->approve($approvedInvoice, $this->user))
        ->toThrow(DomainException::class);
});

// ── markPaid() ────────────────────────────────────────────────────────────────

it('markPaid() sets paid_at', function () {
    $approvedInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $result = $this->service->markPaid($approvedInvoice);

    expect($result->status)->toBe(InvoiceStatus::Paid);
    expect($result->paid_at)->not->toBeNull();
});

it('markPaid() updates PO status to closed when all invoices are paid', function () {
    // The invoice created in beforeEach is pending; delete it so it doesn't interfere
    $this->invoice->forceDelete();

    $approvedInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $this->service->markPaid($approvedInvoice);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

it('markPaid() does NOT close PO if other invoices are still unpaid', function () {
    // $this->invoice is still pending — PO should not close
    $approvedInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $this->service->markPaid($approvedInvoice);

    $this->assertDatabaseMissing('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

it('markPaid() throws DomainException when status is not approved', function () {
    expect(fn () => $this->service->markPaid($this->invoice))
        ->toThrow(DomainException::class);
});

// ── delete() ──────────────────────────────────────────────────────────────────

it('delete() throws DomainException when status is paid', function () {
    $paidInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Paid,
    ]);

    expect(fn () => $this->service->delete($paidInvoice))
        ->toThrow(DomainException::class);
});

it('delete() soft-deletes a pending invoice', function () {
    $this->service->delete($this->invoice);

    $this->assertSoftDeleted('invoices', ['id' => $this->invoice->id]);
});
