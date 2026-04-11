<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePortalPasswordRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function dashboard(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.dashboard', compact('customer'));
    }

    public function show(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.profile.show', compact('customer'));
    }

    public function edit(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.profile.edit', compact('customer'));
    }

    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $customer = $this->service->getByUser(auth()->user());

        $this->service->updateProfile($customer, $request->validated());

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function passwordForm(): View
    {
        return view('portal.profile.password');
    }

    public function updatePassword(ChangePortalPasswordRequest $request): RedirectResponse
    {
        $changed = $this->service->changePassword(
            auth()->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        if (! $changed) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Password changed successfully.');
    }
}
