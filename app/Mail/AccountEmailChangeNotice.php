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
 * Sent to the CURRENT address when a change is requested: the real owner
 * gets a one-click way to stop a hijack before it completes.
 */
class AccountEmailChangeNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $newEmail = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.email_notice.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.email-notice',
            text: 'emails.account.email-notice-text',
            with: [
                // A week: the old inbox may be read late, and the worst misuse
                // of a leaked link is cancelling a change nobody wanted.
                'cancelUrl' => SignedLink::temporary(
                    'settings.email.cancel',
                    now()->addDays(7),
                    ['user' => $this->model->id, 'hash' => sha1($this->newEmail)],
                ),
            ],
        );
    }
}
