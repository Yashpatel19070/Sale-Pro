<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Jobs\SyncCustomerToAvaTaxJob;
use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Customer::query()
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->byStatus(CustomerStatus::from($filters['status']))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Customer
    {
        $customer = Customer::create($data);
        SyncCustomerToAvaTaxJob::dispatch($customer);

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        SyncCustomerToAvaTaxJob::dispatch($customer);

        return $customer;
    }

    public function changeStatus(Customer $customer, CustomerStatus $status): Customer
    {
        $customer->update(['status' => $status]);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Portal self-registration — creates Customer record only.
     *
     * @param  array{name: string, email: string, password: string, phone: string, company_name: ?string}  $data
     */
    public function register(array $data): Customer
    {
        return Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
            'status' => CustomerStatus::Active,
        ]);
    }

    /**
     * Portal profile update — name, phone, company_name only.
     * Email and status are admin-only and are never touched here.
     *
     * @param  array{name: string, phone: string, company_name: ?string}  $data
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
        ]);

        return $customer;
    }

    /**
     * @throws ValidationException if current password is wrong
     */
    public function changePassword(Customer $customer, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $customer->update(['password' => Hash::make($newPassword)]);
    }

    public function verifyEmail(Customer $customer): void
    {
        if ($customer->hasVerifiedEmail()) {
            return;
        }

        $customer->markEmailAsVerified();
        event(new Verified($customer));
    }

    public function sendPasswordReset(Customer $customer): void
    {
        Password::broker('customers')->sendResetLink(['email' => $customer->email]);
    }
}
