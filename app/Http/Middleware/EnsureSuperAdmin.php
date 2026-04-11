<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    private static ?array $superRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$superRoles === null) {
            static::$superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                return Role::where('is_super', true)->pluck('name')->toArray();
            });
        }

        // $user->roles already loaded by LoadUserPermissions — zero DB query
        if (empty(static::$superRoles) || ! $request->user()->hasAnyRole(static::$superRoles)) {
            abort(403, 'Superadmin access required.');
        }

        return $next($request);
    }
}
