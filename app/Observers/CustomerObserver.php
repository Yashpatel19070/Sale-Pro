<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    public function creating(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->created_by = Auth::id();
            $customer->updated_by = Auth::id();
        }
    }

    public function updating(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->updated_by = Auth::id();
        }
    }
}
