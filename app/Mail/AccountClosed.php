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
 * Goodbye note with the way back: the account is soft-deleted, so the link
 * restores it whole for the whole restore window.
 */
class AccountClosed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.closed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.closed',
            text: 'emails.account.closed-text',
            with: [
                'restoreUrl' => SignedLink::temporary(
                    'account.restore',
                    now()->addDays((int) config('atendia.account_restore_days')),
                    ['user' => $this->model->id],
                ),
            ],
        );
    }
}
