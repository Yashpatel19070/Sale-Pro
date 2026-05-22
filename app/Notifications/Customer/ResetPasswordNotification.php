<?php

declare(strict_types=1);

namespace App\Notifications\Customer;

use App\Mail\Customer\ResetPasswordMail;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $resetUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): ResetPasswordMail
    {
        /** @var Customer $notifiable */
        return new ResetPasswordMail($notifiable, $this->resetUrl);
    }
}
