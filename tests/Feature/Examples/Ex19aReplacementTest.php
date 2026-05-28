<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Example 19a — In-Person Complaint → Free Warranty Replacement
|--------------------------------------------------------------------------
|
| Spec: .claude/plans/system-design/examples/ex-19a-counter-replacement.md
| Chains off: ex-19 (ORD-2026-0019) — see tests/Unit/OrderServiceTest.php
|
| SKELETON ONLY. Every assertion below is pulled straight from the doc's
| "Schema + Data" tables. The tests are marked ->skip() because the
| complaint + replacement modules are 0% (see .claude/plans/STATUS.md) —
| ComplaintService / ReplacementService / ComplaintStatus / SerialStatus
| cases do not exist yet. Remove the ->skip() as each module is built TDD.
|
| The point of this file: show the FULL SHAPE of an example-as-a-test and
| prove the "chaining" pattern — reuse ex-19's builder to reach the paid +
| complete starting state, then assert ONLY the complaint/replacement delta.
| The original OrderServiceTest.php is never touched.
|
*/

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const EX19A_SKIP = 'complaint + replacement modules not built yet (0%) — see .claude/plans/STATUS.md';

/**
 * Rebuilds ex-19's end state: ORD-2026-0019 paid (cash $286.86) + complete,
 * ECM-2024 / SN-200 sold. This is the "link" to the prior example — a shared
 * BUILDER, not shared rows. Fresh DB each run.
 *
 * @return array{order: Order, customer: Customer, soldSerial: InventorySerial, admin: User}
 */
function ex19aStartingState(): array
{
    $admin = User::factory()->create();
    $customer = Customer::factory()->create([
        'name' => 'Rachel Park',
        'email' => 'rachel@example.com',
        'tax_exempt' => false,
    ]);
    $location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
    $listing = ProductListing::factory()->active()->for($product)->create();
    $soldSerial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)
        ->create(['serial_number' => 'SN-200']);

    /** @var OrderService $orders */
    $orders = app(OrderService::class);

    $order = $orders->store([
        'customer_id' => $customer->id,
        'source' => 'walk_in',
        'payment_method' => 'cash',
        'billing_address_id' => null,
        'shipping_address_id' => null,
        'shipping' => 0,
        'lines' => [[
            'product_listing_id' => $listing->id,
            'unit_price' => 200.00,
            'tax_amount' => 16.50,
            'fees' => [
                ['name' => 'Programming Fee', 'amount' => 40.00, 'tax_amount' => 3.30],
                ['name' => 'Gas Tuning Fee', 'amount' => 25.00, 'tax_amount' => 2.06],
            ],
        ]],
    ], $admin);

    $orders->recordCashPayment($order, ['amount' => 286.86], $admin);
    $order = $orders->complete($order->fresh(), $admin);

    return compact('order', 'customer', 'soldSerial', 'admin');
}

// ── Sanity: the starting state (this part CAN run — order module is done) ──

it('starts from ex-19 end state: paid + complete order with SN-200 sold', function () {
    $s = ex19aStartingState();

    expect($s['order']->status)->toBe(OrderStatus::Complete);
    expect($s['soldSerial']->fresh()->serial_number)->toBe('SN-200');
    // SN-200 is 'sold' after ex-19 — the complaint flow will pick it up from here.
});

// ── Complaint open (Flow A — counter handover) ────────────────────────────

it('opens a complaint on the existing ECM line when customer returns non-booting unit', function () {
    $s = ex19aStartingState();

    // $complaint = app(\App\Services\ComplaintService::class)
    //     ->open($s['order']->lines->first(), 'ECM not booting', $s['admin']);
    //
    // doc → complaints row:
    // expect($complaint->order_line_id)->toBe($s['order']->lines->first()->id);  // line 47 — NO new line
    // expect($complaint->status)->toBe(ComplaintStatus::Open);
    // expect($complaint->number)->toMatch('/^CMP-\d{4}-\d{4}$/');
})->skip(EX19A_SKIP);

it('records return_in + flips serial to under_examination + sets complaint in_progress on counter handover', function () {
    // doc → inventory_movements id 54 (return_in, NULL → Warehouse A, ref=CMP-xxx)
    // doc → inventory_serials: SN-200 sold → under_examination (skips expected_return)
    // doc → complaints.status → in_progress
})->skip(EX19A_SKIP);

// ── Examination ───────────────────────────────────────────────────────────

it('tech sets examination_result to internal_issues without auto-creating a replacement', function () {
    // doc → complaints.examination_result = internal_issues
    // global rule: NO automatic replacement — replacements row stays absent until admin acts
})->skip(EX19A_SKIP);

// ── Replacement (free — internal fault first occurrence) ──────────────────

it('issues a free counter replacement: new serial sold, no payment row, parent_id null', function () {
    $s = ex19aStartingState();

    // $replacement = app(\App\Services\ReplacementService::class)->issue($complaint, $newSerial, $s['admin']);
    //
    // doc → replacements row:
    // expect($replacement->parent_id)->toBeNull();                 // first in chain
    // expect($replacement->status)->toBe(ReplacementStatus::Delivered);
    // doc → replacement_lines: order_line_id = 47, new_serial_id = SN-201
    // doc → inventory_movements id 55 (replacement_out, Warehouse A → NULL, ref=REP-xxx)
    // doc → inventory_serials: SN-201 in_stock → sold (skips assigned)
    // doc → payments: NO new row (internal_issues first occurrence = free)
    // expect(\App\Models\Payment::count())->toBe(1);               // only ex-19's original cash payment
})->skip(EX19A_SKIP);

// ── Close + scrap faulty unit ─────────────────────────────────────────────

it('closes complaint and scraps the faulty unit', function () {
    // doc → complaints: status=closed, unit_outcome=scrapped, closed_at set
    // doc → inventory_movements id 56 (adjustment, Warehouse A → NULL, ref=CMP-xxx)
    // doc → inventory_serials: SN-200 under_examination → scrapped
})->skip(EX19A_SKIP);

it('leaves the original order status as complete (replacement is not a refund)', function () {
    $s = ex19aStartingState();
    // After the whole complaint→replacement→close flow:
    // expect($s['order']->fresh()->status)->toBe(OrderStatus::Complete);
})->skip(EX19A_SKIP);
