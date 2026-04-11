<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        return Customer::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

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
     * Creates a User account + Customer record in a single transaction.
     *
     * @param  array{name: string, email: string, password: string, phone: string, company_name: ?string, address: string, city: string, state: string, postal_code: string, country: string}  $data
     */
    public function register(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('customer');

            return Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'company_name' => $data['company_name'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
                'status' => CustomerStatus::Active,
            ]);
        });
    }

    public function getByUser(User $user): Customer
    {
        return Customer::where('user_id', $user->id)->firstOrFail();
    }

    /**
     * Portal-only. Does NOT allow email or status changes — those are admin-only.
     *
     * @param  array{name: string, phone: string, company_name: ?string, address: string, city: string, state: string, postal_code: string, country: string}  $data
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'company_name' => $data['company_name'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
            ]);

            $customer->user()->update(['name' => $data['name']]);

            return $customer;
        });
    }

    /**
     * No-op if the customer has no portal user or is already verified.
     */
    public function verifyEmail(Customer $customer): void
    {
        if ($customer->user === null || $customer->user->hasVerifiedEmail()) {
            return;
        }

        $customer->user->markEmailAsVerified();
    }

    /**
     * @throws ValidationException if current password is wrong
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }
}
