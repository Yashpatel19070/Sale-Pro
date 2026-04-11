<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    // Survives across middleware instantiations within the same request.
    // Cache::remember only called once per process when cache is cold.
    private static ?array $adminRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$adminRoles === null) {
            static::$adminRoles = Cache::remember('roles.admin', now()->addHours(6), function () {
                return Role::where('is_admin', true)->pluck('name')->toArray();
            });
        }

        // $user->roles already loaded by LoadUserPermissions — zero DB query
        if (empty(static::$adminRoles) || ! $request->user()->hasAnyRole(static::$adminRoles)) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
