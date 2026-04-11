<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\UserObserver;
use App\Policies\CustomerPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Cache;
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
        Gate::policy(Customer::class, CustomerPolicy::class);

        User::observe(UserObserver::class);
        Customer::observe(CustomerObserver::class);

        // Resolve soft-deleted models for restore routes
        Route::bind('trashedDepartment', fn ($id) => Department::onlyTrashed()->findOrFail($id));
        Route::bind('trashedUser', fn ($id) => User::onlyTrashed()->findOrFail($id));
        Route::bind('trashedCustomer', fn ($id) => Customer::onlyTrashed()->findOrFail($id));

        // Superadmin bypass — any role with is_super=true skips all Gate/policy checks
        $superRoles = null;
        Gate::before(function ($user, $ability) use (&$superRoles) {
            if ($superRoles === null) {
                $superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                    return Role::where('is_super', true)->pluck('name')->toArray();
                });
            }

            if (! empty($superRoles) && $user->hasAnyRole($superRoles)) {
                return true;
            }
        });
    }
}
