<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Department;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\DepartmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        User::observe(UserObserver::class);

        // Resolve soft-deleted models for restore routes
        Route::bind('trashedDepartment', fn ($id) => Department::onlyTrashed()->findOrFail($id));
        Route::bind('trashedUser', fn ($id) => User::onlyTrashed()->findOrFail($id));
    }
}
