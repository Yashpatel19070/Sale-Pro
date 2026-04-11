<?php

use App\Enums\Permission;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

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
    // Static routes MUST come before /{customer} or Laravel captures them as an ID.
    Route::get('customers',        [CustomerController::class, 'index'])  ->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create']) ->name('customers.create');
    Route::post('customers',       [CustomerController::class, 'store'])  ->name('customers.store');

    // Dynamic routes
    Route::get('customers/{customer}',      [CustomerController::class, 'show'])    ->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])    ->name('customers.edit');
    Route::put('customers/{customer}',      [CustomerController::class, 'update'])  ->name('customers.update');
    Route::delete('customers/{customer}',   [CustomerController::class, 'destroy']) ->name('customers.destroy');

    // Custom actions on existing records
    Route::post('customers/{customer}/assign', [CustomerController::class, 'assign'])       ->name('customers.assign');
    Route::post('customers/{customer}/status', [CustomerController::class, 'changeStatus']) ->name('customers.change-status');

    // Restore — {trashedCustomer} resolved via Route::bind in AppServiceProvider (no ->withTrashed() needed)
    Route::post('customers/{trashedCustomer}/restore', [CustomerController::class, 'restore'])
        ->name('customers.restore');

    // Roles (admin + permission-gated)
    Route::middleware(['admin', 'permission:' . Permission::ROLES_VIEW])->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    });

    Route::middleware(['admin', 'permission:' . Permission::ROLES_MANAGE])->group(function () {
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
});

require __DIR__.'/auth.php';
