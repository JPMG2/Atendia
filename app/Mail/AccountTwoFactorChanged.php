<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Security receipt whenever two-step verification is switched on or off. */
class AccountTwoFactorChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public bool $enabled = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->enabled ? 'mail.account.two_factor_on.subject' : 'mail.account.two_factor_off.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.two-factor',
            text: 'emails.account.two-factor-text',
            with: ['copy' => $this->enabled ? 'mail.account.two_factor_on' : 'mail.account.two_factor_off'],
        );
    }
}
