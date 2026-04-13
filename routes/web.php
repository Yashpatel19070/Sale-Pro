<?php

use App\Enums\Permission;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController as PortalSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController as PortalNewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController as PortalPasswordResetController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController as PortalRegisterController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
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

        // Product Categories
        Route::resource('product-categories', ProductCategoryController::class);

        // Products
        Route::resource('products', ProductController::class);
        Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
            ->name('products.toggle-active');
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])
            ->name('products.restore')
            ->withTrashed();

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
