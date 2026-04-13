<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->log('logout');
    }

    public function handleFailed(Failed $event): void
    {
        activity('auth')
            ->withProperties(['ip' => request()->ip()])
            ->log('login-failed');
    }
}
