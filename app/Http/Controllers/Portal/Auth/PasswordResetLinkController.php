<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('customers')->sendResetLink($request->only('email'));

        $customer = Customer::where('email', $request->email)->first();

        if ($customer) {
            activity('mail')
                ->causedBy($customer)
                ->withProperties(['ip' => $request->ip()])
                ->log('password-reset-requested');
        }

        return back()->with('status', 'If that email exists, a reset link has been sent.');
    }
}
