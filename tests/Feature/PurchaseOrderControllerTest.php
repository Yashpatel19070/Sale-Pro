<?php

declare(strict_types=1);

use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([SupplierPermissionSeeder::class, PurchaseOrderPermissionSeeder::class]);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

// ── Index ─────────────────────────────────────────────────────────────────────

it('index returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

it('index returns 200 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

it('index returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->get(route('purchase-orders.index'))
        ->assertForbidden();
});

it('index filters by po_number search', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'po_number' => 'PO-2026-0001',
    ]);
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'po_number' => 'PO-2026-0002',
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index', ['search' => '0001']))
        ->assertOk()
        ->assertSee('PO-2026-0001')
        ->assertDontSee('PO-2026-0002');
});

it('index filters by supplier name search', function () {
    $s1 = Supplier::factory()->create(['name' => 'Acme Corp']);
    $s2 = Supplier::factory()->create(['name' => 'Beta Supply']);
    PurchaseOrder::factory()->create(['supplier_id' => $s1->id, 'created_by_user_id' => $this->admin->id, 'po_number' => 'PO-2026-0011']);
    PurchaseOrder::factory()->create(['supplier_id' => $s2->id, 'created_by_user_id' => $this->admin->id, 'po_number' => 'PO-2026-0012']);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertSee('PO-2026-0011')
        ->assertDontSee('PO-2026-0012');
});

it('index filters by status', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'po_number' => 'PO-2026-0021',
    ]);
    PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'po_number' => 'PO-2026-0022',
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.index', ['status' => 'draft']))
        ->assertOk()
        ->assertSee('PO-2026-0021')
        ->assertDontSee('PO-2026-0022');
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('show returns 200 for admin with lines loaded', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.show', $po))
        ->assertOk()
        ->assertSee($po->po_number);
});

it('show returns 200 for procurement', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->procurement)
        ->get(route('purchase-orders.show', $po))
        ->assertOk();
});

it('show returns 403 for warehouse', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->warehouse)
        ->get(route('purchase-orders.show', $po))
        ->assertForbidden();
});

// ── Create / Store ────────────────────────────────────────────────────────────

it('create returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.create'))
        ->assertOk();
});

it('create returns 200 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('purchase-orders.create'))
        ->assertOk();
});

it('create returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->get(route('purchase-orders.create'))
        ->assertForbidden();
});

it('store creates draft PO with lines', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '10.00'],
            ],
        ])
        ->assertRedirect();

    $po = PurchaseOrder::first();
    expect($po)->not->toBeNull();
    expect($po->status->value)->toBe('draft');
    expect($po->po_number)->toStartWith('PO-');
    expect($po->lines()->count())->toBe(1);
});

it('store requires supplier_id', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
            ],
        ])
        ->assertSessionHasErrors('supplier_id');
});

it('store requires at least one line', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [],
        ])
        ->assertSessionHasErrors('lines');
});

it('store validates line product exists', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => 99999, 'qty_ordered' => 1, 'unit_price' => '10.00'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.product_id');
});

it('store validates line qty_ordered >= 1', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 0, 'unit_price' => '10.00'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.qty_ordered');
});

it('store rejects line qty_ordered > 10000', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 10001, 'unit_price' => '10.00'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.qty_ordered');
});

it('store validates line unit_price >= 0.01', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '0.00'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.unit_price');
});

it('store returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
            ],
        ])
        ->assertForbidden();
});

// ── Edit / Update ─────────────────────────────────────────────────────────────

it('edit returns 200 for admin on draft PO', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.edit', $po))
        ->assertOk();
});

it('edit returns 403 on non-draft PO', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.edit', $po))
        ->assertForbidden();
});

it('update replaces lines on draft PO', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);

    $p2 = Product::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('purchase-orders.update', $po), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $p2->id, 'qty_ordered' => 3, 'unit_price' => '20.00'],
            ],
        ])
        ->assertRedirect(route('purchase-orders.show', $po));

    expect($po->fresh()->lines()->count())->toBe(1);
    expect($po->fresh()->lines()->first()->product_id)->toBe($p2->id);
});

it('update rejects line qty_ordered > 10000', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('purchase-orders.update', $po), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 10001, 'unit_price' => '10.00'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.qty_ordered');
});

it('update returns error when PO is not draft', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('purchase-orders.update', $po), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
            ],
        ])
        ->assertForbidden();
});

it('update returns 403 for procurement', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->procurement)
        ->patch(route('purchase-orders.update', $po), [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
            ],
        ])
        ->assertForbidden();
});

// ── Confirm ───────────────────────────────────────────────────────────────────

it('confirm moves draft to open and sets confirmed_at', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.confirm', $po))
        ->assertRedirect(route('purchase-orders.show', $po));

    $fresh = $po->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->confirmed_at)->not->toBeNull();
});

it('confirm returns error when PO has no lines', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.confirm', $po))
        ->assertRedirect()
        ->assertSessionHasErrors('po');
});

it('confirm returns 403 when PO is not draft', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.confirm', $po))
        ->assertForbidden();
});

it('confirm returns 403 for warehouse', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post(route('purchase-orders.confirm', $po))
        ->assertForbidden();
});

// ── Cancel ────────────────────────────────────────────────────────────────────

it('cancel moves draft to cancelled and stores cancel_notes', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Supplier no longer available'])
        ->assertRedirect(route('purchase-orders.show', $po));

    $fresh = $po->fresh();
    expect($fresh->status->value)->toBe('cancelled');
    expect($fresh->cancel_notes)->toBe('Supplier no longer available');
});

it('cancel moves open PO with no received units to cancelled', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_received' => 0]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Order no longer needed'])
        ->assertRedirect(route('purchase-orders.show', $po));

    expect($po->fresh()->status->value)->toBe('cancelled');
});

it('cancel stores cancel_notes on the PO record', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Testing notes storage here'])
        ->assertRedirect();

    expect($po->fresh()->cancel_notes)->toBe('Testing notes storage here');
});

it('cancel rejects cancel_notes shorter than 10 characters', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Short'])
        ->assertSessionHasErrors('cancel_notes');
});

it('cancel rejects missing cancel_notes', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), [])
        ->assertSessionHasErrors('cancel_notes');
});

it('cancel returns error when PO has received units', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_received' => 1]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Trying to cancel with received units'])
        ->assertRedirect()
        ->assertSessionHasErrors('po');
});

it('cancel returns 403 on partial or closed PO', function () {
    $po = PurchaseOrder::factory()->partial()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Cannot cancel partial PO'])
        ->assertForbidden();
});

it('cancel returns 403 for procurement', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->procurement)
        ->post(route('purchase-orders.cancel', $po), ['cancel_notes' => 'Procurement tries to cancel'])
        ->assertForbidden();
});

// ── Reopen ────────────────────────────────────────────────────────────────────

it('reopen moves closed PO to open and increments reopen_count', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'reopen_count' => 0,
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect(route('purchase-orders.show', $po));

    $fresh = $po->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->reopen_count)->toBe(1);
});

it('reopen first time succeeds for manager', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'reopen_count' => 0,
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect();

    expect($po->fresh()->reopen_count)->toBe(1);
});

it('reopen second time succeeds for manager', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'reopen_count' => 1,
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect();

    expect($po->fresh()->reopen_count)->toBe(2);
});

it('reopen third time returns error for manager', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'reopen_count' => 2,
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect()
        ->assertSessionHasErrors('po');
});

it('reopen third time succeeds for super-admin', function () {
    $superAdmin = User::factory()->create()->assignRole('super-admin');
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
        'reopen_count' => 2,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect(route('purchase-orders.show', $po));

    expect($po->fresh()->reopen_count)->toBe(3);
});

it('reopen returns error when unit is on shelf', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => 'shelf',
        'status' => 'passed',
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertRedirect()
        ->assertSessionHasErrors('po');
});

it('reopen returns 403 when PO is not closed', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->manager)
        ->post(route('purchase-orders.reopen', $po))
        ->assertForbidden();
});

it('reopen returns 403 for procurement', function () {
    $po = PurchaseOrder::factory()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->procurement)
        ->post(route('purchase-orders.reopen', $po))
        ->assertForbidden();
});
