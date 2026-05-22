<?php

declare(strict_types=1);

namespace App\Mail\Customer;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->customer->email, $this->customer->name)],
            subject: 'Reset Your Password',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.reset-password');
    }
}
