# Permissions Module — Middleware

## Four Classes to Create

### 1. `EnsureUserIsActive`
File: `app/Http/Middleware/EnsureUserIsActive.php`

Sits after `auth`. If the authenticated user's status is not `UserStatus::Active`,
log them out and redirect to login with an error.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status !== UserStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
```

---

### 2. `LoadUserPermissions`
File: `app/Http/Middleware/LoadUserPermissions.php`

Runs right after `auth`. Loads `roles.permissions` + `permissions` onto the user
in **one query**. Every subsequent middleware and controller reads from memory — zero
extra DB queries for the rest of the request.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadUserPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $request->user()->load('roles.permissions', 'permissions');
        }

        return $next($request);
    }
}
```

---

### 3. `EnsureIsAdmin`
File: `app/Http/Middleware/EnsureIsAdmin.php`

Reads `is_admin=true` roles from DB, cached for 6 hours. No role names hardcoded.
Requires `LoadUserPermissions` to have run first (roles already on user).

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    private static ?Collection $adminRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$adminRoles === null) {
            static::$adminRoles = Cache::remember('roles.admin', now()->addHours(6), function () {
                return Role::where('is_admin', true)->pluck('name');
            });
        }

        if (! $request->user()->hasAnyRole(static::$adminRoles)) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
```

---

### 4. `EnsureSuperAdmin`
File: `app/Http/Middleware/EnsureSuperAdmin.php`

Same pattern, reads `is_super=true` roles. No MVP superadmin role, but middleware
is ready when needed.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    private static ?Collection $superRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$superRoles === null) {
            static::$superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                return Role::where('is_super', true)->pluck('name');
            });
        }

        if (! $request->user()->hasAnyRole(static::$superRoles)) {
            abort(403, 'Superadmin access required.');
        }

        return $next($request);
    }
}
```
