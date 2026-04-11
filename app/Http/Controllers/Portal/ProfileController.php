<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePortalPasswordRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function dashboard(Request $request): View
    {
        return view('portal.dashboard', ['customer' => $this->customer($request)]);
    }

    public function show(Request $request): View
    {
        return view('portal.profile.show', ['customer' => $this->customer($request)]);
    }

    public function edit(Request $request): View
    {
        return view('portal.profile.edit', ['customer' => $this->customer($request)]);
    }

    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $this->service->updateProfile($this->customer($request), $request->validated());

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
        $this->service->changePassword(
            auth()->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Password changed successfully.');
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->attributes->get('portal_customer')
            ?? $this->service->getByUser(auth()->user());
    }
}
