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

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Customer $customer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->customer->email, $this->customer->name)],
            subject: 'Welcome to '.config('app.name').'!',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.welcome');
    }
}
