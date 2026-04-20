<?php

declare(strict_types=1);

use App\Enums\PoStatus;
use App\Models\PoLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PipelinePermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SupplierPermissionSeeder::class,
        PurchaseOrderPermissionSeeder::class,
        PipelinePermissionSeeder::class,
    ]);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create();
    $this->originalPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->product = Product::factory()->create();
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'product_id' => $this->product->id,
        'unit_price' => '250.00',
    ]);

    $this->returnPo = PurchaseOrder::factory()->returnType()->create([
        'parent_po_id' => $this->originalPo->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'open',
    ]);
});

// ── Index ────────────────────────────────────────────────────────────────────

it('index returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('po-returns.index'))
        ->assertOk()
        ->assertViewHas('returns');
});

it('index returns 200 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('po-returns.index'))
        ->assertOk();
});

it('index returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->get(route('po-returns.index'))
        ->assertForbidden();
});

it('index only shows type=return POs', function () {
    $unrelatedPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->admin)
        ->get(route('po-returns.index'))
        ->assertOk()
        ->assertSee($this->returnPo->po_number)
        ->assertDontSee(route('po-returns.show', $unrelatedPo));
});

it('index filters by search (po_number)', function () {
    $other = PurchaseOrder::factory()->returnType()->create([
        'po_number' => 'PO-FIND-9999',
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->originalPo->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('po-returns.index', ['search' => 'PO-FIND-9999']))
        ->assertOk()
        ->assertSee('PO-FIND-9999')
        ->assertDontSee($this->returnPo->po_number);
});

it('index filters by status', function () {
    $closedReturn = PurchaseOrder::factory()->returnType()->closed()->create([
        'parent_po_id' => $this->originalPo->id,
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('po-returns.index', ['status' => 'closed']))
        ->assertOk()
        ->assertSee($closedReturn->po_number)
        ->assertDontSee($this->returnPo->po_number);
});

// ── Show ─────────────────────────────────────────────────────────────────────

it('show returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('po-returns.show', $this->returnPo))
        ->assertOk()
        ->assertViewHas('purchaseOrder');
});

it('show returns 200 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('po-returns.show', $this->returnPo))
        ->assertOk();
});

it('show returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->get(route('po-returns.show', $this->returnPo))
        ->assertForbidden();
});

it('show returns 404 for a purchase-type PO', function () {
    $this->actingAs($this->admin)
        ->get(route('po-returns.show', $this->originalPo))
        ->assertNotFound();
});

it('show loads parent PO and supplier', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('po-returns.show', $this->returnPo));

    $response->assertOk();
    $po = $response->viewData('purchaseOrder');
    expect($po->relationLoaded('parentPo'))->toBeTrue();
    expect($po->relationLoaded('supplier'))->toBeTrue();
});

// ── Close ────────────────────────────────────────────────────────────────────

it('close sets status to closed and sets closed_at', function () {
    $this->actingAs($this->manager)
        ->post(route('po-returns.close', $this->returnPo))
        ->assertRedirect(route('po-returns.show', $this->returnPo));

    $this->returnPo->refresh();
    expect($this->returnPo->status)->toBe(PoStatus::Closed);
    expect($this->returnPo->closed_at)->not->toBeNull();
});

it('close redirects to show with success message', function () {
    $this->actingAs($this->manager)
        ->post(route('po-returns.close', $this->returnPo))
        ->assertRedirect(route('po-returns.show', $this->returnPo))
        ->assertSessionHas('success');
});

it('close returns 404 for purchase-type PO', function () {
    $this->actingAs($this->manager)
        ->post(route('po-returns.close', $this->originalPo))
        ->assertNotFound();
});

it('close returns 403 when return PO is already closed', function () {
    $closedReturn = PurchaseOrder::factory()->returnType()->closed()->create([
        'parent_po_id' => $this->originalPo->id,
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->manager)
        ->post(route('po-returns.close', $closedReturn))
        ->assertForbidden();
});

it('close returns 403 for procurement', function () {
    $this->actingAs($this->procurement)
        ->post(route('po-returns.close', $this->returnPo))
        ->assertForbidden();
});

it('close succeeds for manager', function () {
    $this->actingAs($this->manager)
        ->post(route('po-returns.close', $this->returnPo))
        ->assertRedirect();

    expect($this->returnPo->fresh()->status)->toBe(PoStatus::Closed);
});
