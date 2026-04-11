<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Auth\RegisterRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $customer = $this->service->register($request->validated());

        Auth::login($customer->user);

        $customer->user->sendEmailVerificationNotification();

        return redirect()->route('portal.verification.notice');
    }
}
