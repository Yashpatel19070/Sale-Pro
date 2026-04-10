<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Department;
use App\Policies\DepartmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);

        // Resolve soft-deleted departments for the restore route
        Route::bind('trashedDepartment', fn ($id) => Department::onlyTrashed()->findOrFail($id));
    }
}
