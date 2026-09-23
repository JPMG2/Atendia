<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\SignedLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the NEW address: the switch only happens once its owner proves
 * the inbox is theirs by clicking.
 */
class AccountEmailChangeRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.email_change.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.email-change',
            text: 'emails.account.email-change-text',
            with: [
                // Keyed to the pending address: a link sent before a second
                // change request stops working the moment that one lands.
                'confirmUrl' => SignedLink::temporary(
                    'settings.email.confirm',
                    now()->addMinutes((int) config('atendia.email_change_minutes')),
                    ['user' => $this->model->id, 'hash' => sha1((string) $this->model->pending_email)],
                ),
            ],
        );
    }
}
