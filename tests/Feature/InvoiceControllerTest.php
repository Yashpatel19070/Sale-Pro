<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $this->invoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Pending,
    ]);
});

// ── Guest redirects ───────────────────────────────────────────────────────────

it('guest is redirected to login on invoice create', function () {
    $this->get(route('purchase-orders.invoices.create', $this->po))
        ->assertRedirect(route('login'));
});

// ── Sales user (read-only) ────────────────────────────────────────────────────

it('sales user cannot create an invoice', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
    ]);

    $this->actingAs($salesUser)
        ->get(route('purchase-orders.invoices.create', $this->po))
        ->assertForbidden();
});

// ── Admin create ──────────────────────────────────────────────────────────────

it('admin can view the create invoice form', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.invoices.create', $this->po))
        ->assertOk();
});

// ── Admin store ───────────────────────────────────────────────────────────────

it('admin can store an invoice with status pending', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
    ]);

    $data = [
        'invoice_number' => 'INV-TEST-001',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'amount' => 100.00,
        'notes' => null,
    ];

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.store', $this->po), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'purchase_order_id' => $this->po->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => InvoiceStatus::Pending->value,
    ]);
});

it('cannot create an invoice for a draft PO', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
    ]);

    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $data = [
        'invoice_number' => 'INV-DRAFT-001',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'amount' => 100.00,
        'notes' => null,
    ];

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.store', $draftPo), $data)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin approve ─────────────────────────────────────────────────────────────

it('admin can approve a pending invoice', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_APPROVE,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.approve', [$this->po, $this->invoice]));

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => InvoiceStatus::Approved->value,
    ]);
});

it('cannot approve a paid invoice', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_APPROVE,
    ]);

    $paidInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Paid,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.approve', [$this->po, $paidInvoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin markPaid ────────────────────────────────────────────────────────────

it('admin can mark an approved invoice as paid', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_MARK_PAID,
    ]);

    $approvedInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Approved,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.markPaid', [$this->po, $approvedInvoice]));

    $this->assertDatabaseHas('invoices', [
        'id' => $approvedInvoice->id,
        'status' => InvoiceStatus::Paid->value,
    ]);

    expect(Invoice::find($approvedInvoice->id)->paid_at)->not->toBeNull();
});

it('cannot mark a pending invoice as paid', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_MARK_PAID,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.invoices.markPaid', [$this->po, $this->invoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin destroy ─────────────────────────────────────────────────────────────

it('admin can soft delete a pending invoice', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_DELETE,
    ]);

    $this->actingAs($admin)
        ->delete(route('purchase-orders.invoices.destroy', [$this->po, $this->invoice]));

    $this->assertSoftDeleted('invoices', ['id' => $this->invoice->id]);
});

it('cannot delete a paid invoice', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_DELETE,
    ]);

    $paidInvoice = Invoice::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => InvoiceStatus::Paid,
    ]);

    $this->actingAs($admin)
        ->delete(route('purchase-orders.invoices.destroy', [$this->po, $paidInvoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('invoices', ['id' => $paidInvoice->id]);
});

// ── Admin show ────────────────────────────────────────────────────────────────

it('admin can view the invoice show page', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.invoices.show', [$this->po, $this->invoice]))
        ->assertOk();
});
