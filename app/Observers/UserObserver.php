<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function creating(User $user): void
    {
        if (Auth::check()) {
            $user->created_by = Auth::id();
            $user->updated_by = Auth::id();
        }
    }

    public function updating(User $user): void
    {
        if (Auth::check()) {
            $user->updated_by = Auth::id();
        }
    }
}
