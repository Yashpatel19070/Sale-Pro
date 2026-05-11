<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit', 'E2E');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// ── Global test helpers ──────────────────────────────────────────────────────

use App\Enums\Permission;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryMovementService;

function poAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_CREATE,
        Permission::PURCHASE_ORDERS_UPDATE,
        Permission::PURCHASE_ORDERS_DELETE,
        Permission::PURCHASE_ORDERS_RESTORE,
        Permission::PURCHASE_ORDERS_SUBMIT,
        Permission::PURCHASE_ORDERS_APPROVE,
        Permission::PURCHASE_ORDERS_REJECT,
        Permission::PURCHASE_ORDERS_CANCEL,
        Permission::PURCHASE_ORDERS_QUALITY_CHECK,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
        Permission::GOODS_RECEIPTS_DELETE,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
        Permission::INVOICES_APPROVE,
        Permission::INVOICES_MARK_PAID,
        Permission::INVOICES_DELETE,
    ]);

    return $user;
}

function poSalesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
    ]);

    return $user;
}

function createCompletedGrnWithQcPassed(
    ?Supplier $supplier = null,
    ?User $receiver = null,
): array {
    $supplier ??= Supplier::factory()->create();
    $receiver ??= User::factory()->create();
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()
        ->for($supplier)
        ->qualityCheck()
        ->has(PurchaseOrderLine::factory()->for($product)->state(['qty_ordered' => 5, 'qty_received' => 5]))
        ->create();
    $grn = GoodsReceipt::factory()
        ->for($po)
        ->complete()
        ->has(
            GoodsReceiptLine::factory()
                ->for($po->lines->first(), 'purchaseOrderLine')
                ->qcSubmitted(['qty_received' => 5, 'qty_passed' => 5, 'qty_failed' => 0])
        )
        ->create(['received_by' => $receiver->id]);

    return [$po, $grn];
}

function createGrnWithSerialsAssigned(?Supplier $supplier = null): array
{
    $user = User::factory()->create()->tap(fn ($u) => $u->givePermissionTo([
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]));
    $location = InventoryLocation::factory()->create();
    [$po, $grn] = createCompletedGrnWithQcPassed(supplier: $supplier);

    $serials = app(InventoryMovementService::class)->bulkReceiveFromGrn($grn, [
        'lines' => [
            [
                'goods_receipt_line_id' => $grn->lines->first()->id,
                'inventory_location_id' => $location->id,
                'purchase_price' => '100.00',
            ],
        ],
    ], $user);

    return [$po, $grn, $serials];
}
