<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function __construct(private readonly CustomerAddressService $service) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        return view('portal.addresses.index', [
            'addresses' => $this->service->list($customer),
        ]);
    }

    public function create(): View
    {
        return view('portal.addresses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate($this->rules($customer->id));

        $this->service->store($customer, $validated);

        return redirect()->route('portal.addresses.index')
            ->with('success', 'Address added.');
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $customer = $request->user('customer');
        $address = $customer->addresses()->findOrFail($address->id);

        return view('portal.addresses.edit', compact('address'));
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $request->user('customer');
        $address = $customer->addresses()->findOrFail($address->id);

        $validated = $request->validate($this->rules($customer->id, $address->id));

        $this->service->update($address, $validated);

        return redirect()->route('portal.addresses.index')
            ->with('success', 'Address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $request->user('customer');
        $address = $customer->addresses()->findOrFail($address->id);

        try {
            $this->service->delete($address);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.addresses.index')
            ->with('success', 'Address deleted.');
    }

    public function setDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $request->user('customer');
        $address = $customer->addresses()->findOrFail($address->id);

        $this->service->setDefault($address);

        return redirect()->route('portal.addresses.index')
            ->with('success', 'Default address updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $customerId, ?int $ignoreId = null): array
    {
        return [
            'label' => [
                'required', 'string', 'max:50',
                Rule::unique('customer_addresses', 'label')
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:10'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
        ];
    }
}
