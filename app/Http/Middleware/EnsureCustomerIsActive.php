<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Services\CustomerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function __construct(private readonly CustomerService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $customer = $this->service->getByUser($user);

        if ($customer->status !== CustomerStatus::Active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('portal.login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        $request->attributes->set('portal_customer', $customer);

        return $next($request);
    }
}
