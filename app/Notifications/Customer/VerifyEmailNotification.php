<?php

declare(strict_types=1);

namespace App\Notifications\Customer;

use App\Mail\Customer\VerifyEmailMail;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $verificationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): VerifyEmailMail
    {
        /** @var Customer $notifiable */
        return new VerifyEmailMail($notifiable, $this->verificationUrl);
    }
}
