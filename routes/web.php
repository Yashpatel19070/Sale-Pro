<?php

use App\Enums\Permission;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryLocationController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InventorySerialController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MailTestController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController as PortalSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController as PortalNewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController as PortalPasswordResetController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController as PortalRegisterController;
use App\Http\Controllers\Portal\CustomerAddressController as PortalAddressController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Root ─────────────────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('portal.login'));

// ── Admin Routes ──────────────────────────────────────────────────────────────
Route::prefix('admin')->group(function () {

    Route::get('/dashboard', fn () => view('dashboard'))
        ->middleware(['auth', 'load_perms', 'verified', 'active'])
        ->name('dashboard');

    Route::middleware(['auth', 'load_perms', 'verified', 'active'])->group(function () {

        // Profile (Breeze)
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Users
        Route::resource('users', UserController::class);
        Route::post('users/{user}/change-status', [UserController::class, 'changeStatus'])
            ->name('users.change-status');
        Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset')
            ->middleware('throttle:5,1');
        Route::post('users/{trashedUser}/restore', [UserController::class, 'restore'])
            ->name('users.restore');

        // Departments
        Route::resource('departments', DepartmentController::class);
        Route::post('departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive'])
            ->name('departments.toggle-active');
        Route::post('departments/{trashedDepartment}/restore', [DepartmentController::class, 'restore'])
            ->name('departments.restore');

        // Customers
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::get('/create', [CustomerController::class, 'create'])->name('create');
            Route::post('/', [CustomerController::class, 'store'])->name('store');
            Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
            Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
            Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
            Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
            Route::patch('/{customer}/status', [CustomerController::class, 'changeStatus'])->name('changeStatus');
            Route::post('/{customer}/verify-email', [CustomerController::class, 'verifyEmail'])->name('verifyEmail');
            Route::post('/{customer}/send-password-reset', [CustomerController::class, 'sendPasswordReset'])
                ->name('sendPasswordReset')
                ->middleware('throttle:5,1');
        });

        // Customer Addresses
        Route::prefix('customers/{customer}/addresses')->name('customer-addresses.')->scopeBindings()->group(function () {
            Route::get('/', [CustomerAddressController::class, 'index'])->name('index');
            Route::get('/create', [CustomerAddressController::class, 'create'])->name('create');
            Route::post('/', [CustomerAddressController::class, 'store'])->name('store');
            Route::get('/{address}/edit', [CustomerAddressController::class, 'edit'])->name('edit');
            Route::put('/{address}', [CustomerAddressController::class, 'update'])->name('update');
            Route::delete('/{address}', [CustomerAddressController::class, 'destroy'])->name('destroy');
            Route::patch('/{address}/default', [CustomerAddressController::class, 'setDefault'])->name('setDefault');
        });

        // Suppliers
        Route::prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('index');
            Route::get('/create', [SupplierController::class, 'create'])->name('create');
            Route::post('/', [SupplierController::class, 'store'])->name('store');
            Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
            Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
            Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
            Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
            Route::patch('/{supplier}/status', [SupplierController::class, 'changeStatus'])->name('changeStatus');
            Route::post('/{supplier}/restore', [SupplierController::class, 'restore'])->name('restore')->withTrashed();
        });

        // Product Categories
        Route::resource('product-categories', ProductCategoryController::class);

        // Products
        Route::resource('products', ProductController::class);
        Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
            ->name('products.toggle-active');
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])
            ->name('products.restore')
            ->withTrashed();

        // Product Listings
        Route::get('product-listings/search', [ProductListingController::class, 'search'])->name('product-listings.search');
        Route::resource('product-listings', ProductListingController::class);
        Route::post('product-listings/{productListing}/toggle-visibility', [ProductListingController::class, 'toggleVisibility'])
            ->name('product-listings.toggle-visibility');
        Route::post('product-listings/{productListing}/restore', [ProductListingController::class, 'restore'])
            ->name('product-listings.restore')
            ->withTrashed();

        // Inventory Locations
        Route::prefix('inventory-locations')->name('inventory-locations.')->group(function () {
            Route::get('/', [InventoryLocationController::class, 'index'])->name('index');
            Route::get('/create', [InventoryLocationController::class, 'create'])->name('create');
            Route::post('/', [InventoryLocationController::class, 'store'])->name('store');
            Route::get('/search', [InventoryLocationController::class, 'search'])->name('search');
            Route::get('/{inventoryLocation}', [InventoryLocationController::class, 'show'])->name('show');
            Route::get('/{inventoryLocation}/edit', [InventoryLocationController::class, 'edit'])->name('edit');
            Route::put('/{inventoryLocation}', [InventoryLocationController::class, 'update'])->name('update');
            Route::delete('/{inventoryLocation}', [InventoryLocationController::class, 'destroy'])->name('destroy');
            Route::post('/{inventoryLocation}/restore', [InventoryLocationController::class, 'restore'])->name('restore')->withTrashed();
        });

        // Inventory Serials
        Route::prefix('inventory-serials')->name('inventory-serials.')->group(function () {
            Route::get('/', [InventorySerialController::class, 'index'])->name('index');
            Route::get('/create', [InventorySerialController::class, 'create'])->name('create');
            Route::post('/', [InventorySerialController::class, 'store'])->name('store');
            Route::get('/search', [InventorySerialController::class, 'search'])->name('search');
            Route::get('/{inventorySerial}', [InventorySerialController::class, 'show'])->name('show');
            Route::get('/{inventorySerial}/edit', [InventorySerialController::class, 'edit'])->name('edit');
            Route::put('/{inventorySerial}', [InventorySerialController::class, 'update'])->name('update');
        });

        // Inventory Movements
        Route::prefix('inventory-movements')->name('inventory-movements.')->group(function () {
            Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
            Route::get('/create', [InventoryMovementController::class, 'create'])->name('create');
            Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
            // NO edit, update, destroy — movements are immutable
            // Bulk receive — admin/manager only
            Route::get('/bulk-receive', [InventoryMovementController::class, 'bulkReceive'])->name('bulk-receive');
            Route::post('/bulk-receive', [InventoryMovementController::class, 'storeBulkReceive'])->name('bulk-receive.store');
            Route::get('/bulk-receive/print', [InventoryMovementController::class, 'printBulkReceive'])->name('bulk-receive-print');
        });

        // Serial timeline — nested under inventory-serials
        Route::get(
            'inventory-serials/{inventorySerial}/movements',
            [InventoryMovementController::class, 'forSerial']
        )->name('inventory-serials.movements');

        // Inventory — stock visibility (read only)
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::get('/{product}', [InventoryController::class, 'showBySku'])->name('by-sku');
            Route::get('/{product}/{location}', [InventoryController::class, 'showBySkuAtLocation'])->name('by-sku-at-location');
        });

        // Audit Log (read-only)
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/{activity}', [AuditLogController::class, 'show'])->name('audit-log.show');

        // Mail Tester (admin/superadmin only)
        Route::get('mail-test', [MailTestController::class, 'index'])->name('mail-test.index');
        Route::post('mail-test', [MailTestController::class, 'send'])->name('mail-test.send');

        // Purchase Orders
        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
            Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
            Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
            Route::post('/{purchaseOrder}/restore', [PurchaseOrderController::class, 'restore'])->name('restore')->withTrashed();
            Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
            Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
            Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('reject');
            Route::post('/{purchaseOrder}/on-the-way', [PurchaseOrderController::class, 'markOnTheWay'])->name('markOnTheWay');
            Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
            Route::post('/{purchaseOrder}/quality-check', [PurchaseOrderController::class, 'qualityCheck'])->name('qualityCheck');
            Route::get('/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('print');

            Route::prefix('/{purchaseOrder}/goods-receipts')->name('goods-receipts.')->group(function () {
                Route::get('/create', [GoodsReceiptController::class, 'create'])->name('create');
                Route::post('/', [GoodsReceiptController::class, 'store'])->name('store');
                Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('show');
                Route::get('/{goodsReceipt}/edit', [GoodsReceiptController::class, 'edit'])->name('edit');
                Route::put('/{goodsReceipt}', [GoodsReceiptController::class, 'update'])->name('update');
                Route::post('/{goodsReceipt}/complete', [GoodsReceiptController::class, 'complete'])->name('complete');
                Route::post('/{goodsReceipt}/qc', [GoodsReceiptController::class, 'submitQc'])->name('submitQc');
                Route::get('/{goodsReceipt}/assign-serials', [GoodsReceiptController::class, 'assignSerials'])->name('assignSerials');
                Route::post('/{goodsReceipt}/assign-serials', [GoodsReceiptController::class, 'storeSerials'])->name('storeSerials');
                Route::delete('/{goodsReceipt}', [GoodsReceiptController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('/{purchaseOrder}/invoices')->name('invoices.')->group(function () {
                Route::get('/create', [InvoiceController::class, 'create'])->name('create');
                Route::post('/', [InvoiceController::class, 'store'])->name('store');
                Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
                Route::post('/{invoice}/approve', [InvoiceController::class, 'approve'])->name('approve');
                Route::post('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('markPaid');
                Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
            });
        });

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/create', [OrderController::class, 'create'])->name('create');
            Route::post('/', [OrderController::class, 'store'])->name('store');
            Route::post('/tax-preview', [OrderController::class, 'taxPreview'])->name('tax-preview');
            Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            Route::post('/{order}/pay', [OrderController::class, 'pay'])->name('pay');
            Route::post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
            Route::post('/{order}/deliver', [OrderController::class, 'deliver'])->name('deliver');
            Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
            Route::put('/{order}', [OrderController::class, 'update'])->name('update');
            Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
        });

        // Roles (admin + permission-gated)
        Route::middleware(['admin', 'permission:'.Permission::ROLES_VIEW])->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        });

        Route::middleware(['admin', 'permission:'.Permission::ROLES_MANAGE])->group(function () {
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
    });
});

// ── Portal Routes ─────────────────────────────────────────────────────────────
Route::name('portal.')->group(function () {

    // Guest only (customer guard)
    Route::middleware('guest:customer')->group(function () {
        Route::get('/register', [PortalRegisterController::class, 'create'])->name('register');
        Route::post('/register', [PortalRegisterController::class, 'store'])->name('register.store')->middleware('throttle:register');
        Route::get('/login', [PortalSessionController::class, 'create'])->name('login');
        Route::post('/login', [PortalSessionController::class, 'store'])->name('login.store')->middleware('throttle:login');
        Route::get('/forgot-password', [PortalPasswordResetController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PortalPasswordResetController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
        Route::get('/reset-password/{token}', [PortalNewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [PortalNewPasswordController::class, 'store'])->name('password.update');
    });

    // Authenticated (customer guard)
    Route::middleware(['auth:customer', 'verified:portal.verification.notice', 'customer.active'])->group(function () {

        // Email verification — exempt from the verified middleware
        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
            ->name('verification.notice')
            ->withoutMiddleware('verified:portal.verification.notice');

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify')
            ->middleware('signed')
            ->withoutMiddleware('verified:portal.verification.notice');

        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->name('verification.send')
            ->middleware('throttle:6,1')
            ->withoutMiddleware('verified:portal.verification.notice');

        Route::post('/logout', [PortalSessionController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', fn () => view('portal.dashboard', [
            'customer' => auth('customer')->user(),
        ]))->name('dashboard');

        // Profile
        Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile.show');
        Route::get('/profile/edit', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/password', [PortalProfileController::class, 'passwordForm'])->name('profile.password');
        Route::put('/profile/password', [PortalProfileController::class, 'updatePassword'])->name('profile.password.update');

        // Addresses
        Route::prefix('addresses')->name('addresses.')->group(function () {
            Route::get('/', [PortalAddressController::class, 'index'])->name('index');
            Route::get('/create', [PortalAddressController::class, 'create'])->name('create');
            Route::post('/', [PortalAddressController::class, 'store'])->name('store');
            Route::get('/{address}/edit', [PortalAddressController::class, 'edit'])->name('edit');
            Route::put('/{address}', [PortalAddressController::class, 'update'])->name('update');
            Route::delete('/{address}', [PortalAddressController::class, 'destroy'])->name('destroy');
            Route::patch('/{address}/default', [PortalAddressController::class, 'setDefault'])->name('setDefault');
        });
    });
});

require __DIR__.'/auth.php';
