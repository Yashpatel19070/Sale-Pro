# Permissions Module — Gate Superadmin Bypass

## `app/Providers/AppServiceProvider.php`

Add `Gate::before()` in `boot()`. Reads `is_super` flag from cached role list.
Any user whose role has `is_super=true` bypasses ALL Gate/policy checks.

```php
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    $superRoles = null;

    Gate::before(function ($user, $ability) use (&$superRoles) {
        if ($superRoles === null) {
            $superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                return Role::where('is_super', true)->pluck('name');
            });
        }

        if ($user->hasAnyRole($superRoles)) {
            return true;
        }
    });
}
```

## Notes

- `$superRoles` is `null`-initialized outside the closure so the cache is only hit once
  per request, not once per Gate check.
- No MVP superadmin role exists yet — this is a no-op until a role with `is_super=true`
  is created.
- Returning `true` from `Gate::before()` short-circuits all policy and Gate checks for
  that user on that request.
