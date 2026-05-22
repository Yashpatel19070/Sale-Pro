<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePortalPasswordRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function show(Request $request): View
    {
        $customer = $request->user('customer');
        $defaultAddress = $customer->addresses()->where('is_default', true)->first();

        return view('portal.profile.show', compact('customer', 'defaultAddress'));
    }

    public function edit(Request $request): View
    {
        return view('portal.profile.edit', ['customer' => $request->user('customer')]);
    }

    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $this->service->updateProfile($request->user('customer'), $request->validated());

        return redirect()->route('portal.profile.show')->with('success', 'Profile updated successfully.');
    }

    public function passwordForm(): View
    {
        return view('portal.profile.password');
    }

    public function updatePassword(ChangePortalPasswordRequest $request): RedirectResponse
    {
        $this->service->changePassword(
            $request->user('customer'),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return redirect()->route('portal.profile.show')->with('success', 'Password changed successfully.');
    }
}
