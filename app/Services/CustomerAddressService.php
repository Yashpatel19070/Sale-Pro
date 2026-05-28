<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncCustomerToAvaTaxJob;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function list(Customer $customer): Collection
    {
        return $customer->addresses()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    public function store(Customer $customer, array $data): CustomerAddress
    {
        $address = $customer->addresses()->create($data);

        if (! $customer->addresses()->where('id', '!=', $address->id)->exists()) {
            $address->forceFill(['is_default' => true])->save();
        }

        SyncCustomerToAvaTaxJob::dispatch($customer);

        return $address;
    }

    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        $address->update($data);

        SyncCustomerToAvaTaxJob::dispatch($address->customer);

        return $address->fresh();
    }

    public function setDefault(CustomerAddress $address): CustomerAddress
    {
        DB::transaction(function () use ($address) {
            CustomerAddress::where('customer_id', $address->customer_id)
                ->lockForUpdate()
                ->pluck('id');

            CustomerAddress::where('customer_id', $address->customer_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->forceFill(['is_default' => true])->save();
        });

        // Dispatch AFTER the transaction commits — never inside (service.md).
        SyncCustomerToAvaTaxJob::dispatch($address->customer);

        return $address;
    }

    public function delete(CustomerAddress $address): void
    {
        if ($address->is_default) {
            throw new \RuntimeException('Cannot delete the default address.');
        }

        $customer = $address->customer;
        $address->delete();

        SyncCustomerToAvaTaxJob::dispatch($customer);
    }
}
