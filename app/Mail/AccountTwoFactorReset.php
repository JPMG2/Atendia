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

/** Tells a person of the team that the owner took their second step away. */
class AccountTwoFactorReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.account.two_factor_reset.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.two-factor',
            text: 'emails.account.two-factor-text',
            with: ['copy' => 'mail.account.two_factor_reset'],
        );
    }
}
