<?php

use App\Enums\Permission;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryLocationController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InventorySerialController;
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController as PortalSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController as PortalNewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController as PortalPasswordResetController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController as PortalRegisterController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\ProfileController;
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

// ── Portal Guest Routes ───────────────────────────────────────────────────────
Route::middleware('guest')->name('portal.')->group(function () {
    Route::get('/register', [PortalRegisterController::class, 'create'])->name('register');
    Route::post('/register', [PortalRegisterController::class, 'store'])->name('register.store')->middleware('throttle:register');
    Route::get('/login', [PortalSessionController::class, 'create'])->name('login');
    Route::post('/login', [PortalSessionController::class, 'store'])->name('login.store')->middleware('throttle:login');
    Route::get('/forgot-password', [PortalPasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PortalPasswordResetController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
    Route::get('/reset-password/{token}', [PortalNewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [PortalNewPasswordController::class, 'store'])->name('password.update');
});

// ── Portal Authenticated Routes ───────────────────────────────────────────────
Route::middleware(['auth', 'verified:portal.verification.notice', 'role:customer', 'active', 'customer.active'])
    ->name('portal.')->group(function () {

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

        // Logout
        Route::post('/logout', [PortalSessionController::class, 'destroy'])->name('logout');

        // Dashboard
        Route::get('/dashboard', [PortalProfileController::class, 'dashboard'])->name('dashboard');

        // Profile
        Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile.show');
        Route::get('/profile/edit', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/password', [PortalProfileController::class, 'passwordForm'])->name('profile.password');
        Route::put('/profile/password', [PortalProfileController::class, 'updatePassword'])->name('profile.password.update');
    });

require __DIR__.'/auth.php';
