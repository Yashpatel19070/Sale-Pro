<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\Models\Customer;
use App\Models\Department;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductListing;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\InventoryLocationPolicy;
use App\Policies\InventoryMovementPolicy;
use App\Policies\InventorySerialPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductListingPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Activity::class, AuditLogPolicy::class);
        Gate::policy(ProductListing::class, ProductListingPolicy::class);
        Gate::policy(InventoryLocation::class, InventoryLocationPolicy::class);
        Gate::policy(InventorySerial::class, InventorySerialPolicy::class);
        Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PoUnitJob::class, PoUnitJobPolicy::class);

        // Auth event listeners — log login, logout, failed attempts
        Event::listen(Login::class, [LogAuthActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthActivity::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthActivity::class, 'handleFailed']);

        User::observe(UserObserver::class);

        // Resolve soft-deleted models for restore routes
        Route::bind('trashedDepartment', fn ($id) => Department::onlyTrashed()->findOrFail($id));
        Route::bind('trashedUser', fn ($id) => User::onlyTrashed()->findOrFail($id));

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

        // ── Rate limiting ─────────────────────────────────────────────────────

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip());
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip());
        });

        // ── Portal notification URLs ──────────────────────────────────────────

        // Only redirect to portal routes for users with the customer role.
        // Admin/staff password resets and email verifications use the default routes.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            if ($notifiable->hasRole('customer')) {
                return route('portal.password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]);
            }

            return route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            if ($notifiable->hasRole('customer')) {
                return URL::temporarySignedRoute(
                    'portal.verification.verify',
                    now()->addMinutes(config('auth.verification.expire', 60)),
                    [
                        'id' => $notifiable->getKey(),
                        'hash' => sha1($notifiable->getEmailForVerification()),
                    ]
                );
            }

            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });
    }
}
