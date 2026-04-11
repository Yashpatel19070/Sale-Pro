# Permissions Module — Routes

## Updated `routes/web.php`

Replace the single `['auth', 'verified']` group with the full middleware stack
per the skill: `auth → load_perms → verified → active`.

Add a roles management sub-group gated by `admin` + `permission:roles.manage`.

```php
<?php

use App\Enums\Permission;
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

    // Roles — admin only, requires roles.manage permission
    Route::middleware(['admin', 'permission:' . Permission::ROLES_VIEW])
        ->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        });

    Route::middleware(['admin', 'permission:' . Permission::ROLES_MANAGE])
        ->group(function () {
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
});

require __DIR__.'/auth.php';
```

## Middleware Stack Order

```
auth → load_perms → verified → active → [admin] → [permission:xxx] → Controller
```

- `load_perms` eager-loads roles + permissions once. Everything after: zero DB queries.
- `active` blocks suspended users before they reach any controller.
- `admin` + `permission:roles.manage` double-gates roles management.
