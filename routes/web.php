<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Departments
    Route::resource('departments', DepartmentController::class);
    Route::post('departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive'])
        ->name('departments.toggle-active');
    Route::post('departments/{trashedDepartment}/restore', [DepartmentController::class, 'restore'])
        ->name('departments.restore');
});

require __DIR__.'/auth.php';
