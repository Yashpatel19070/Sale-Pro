<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $customer = $request->user('customer');

        if ((string) $customer->getKey() !== $id) {
            abort(403);
        }

        if (! hash_equals($hash, sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
            event(new Verified($customer));

            activity('mail')
                ->causedBy($customer)
                ->withProperties(['ip' => $request->ip()])
                ->log('email-verified');
        }

        return redirect()->route('portal.dashboard')->with('success', 'Email verified. Welcome!');
    }

    public function resend(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        $customer->sendEmailVerificationNotification();

        activity('mail')
            ->causedBy($customer)
            ->withProperties(['ip' => $request->ip()])
            ->log('verification-resent');

        return back()->with('status', 'verification-link-sent');
    }
}
