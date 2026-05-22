<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Mail\Customer\WelcomeMail;
use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Mail;

class SendWelcomeMail
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof Customer) {
            return;
        }

        Mail::send(new WelcomeMail($event->user));
    }
}
