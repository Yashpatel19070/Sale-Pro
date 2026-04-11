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
            // Single query — loads roles AND their permissions into memory.
            // Every hasAnyRole(), can(), hasPermissionTo() call after this is free.
            $request->user()->load('roles.permissions', 'permissions');
        }

        return $next($request);
    }
}
