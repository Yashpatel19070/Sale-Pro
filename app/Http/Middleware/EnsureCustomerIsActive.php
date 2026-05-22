<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return $next($request);
        }

        if ($customer->status !== CustomerStatus::Active) {
            Auth::guard('customer')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('portal.login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        return $next($request);
    }
}
