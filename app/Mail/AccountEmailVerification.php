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

/** The button that turns "unverified" green in the security checkup. */
class AccountEmailVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.verify.subject'),
        );
    }

    /** Works from any device, signed in or not: the hash ties it to this exact address. */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account.verify',
            text: 'emails.account.verify-text',
            with: [
                'verifyUrl' => SignedLink::temporary(
                    'settings.email.verify',
                    now()->addMinutes((int) config('atendia.email_change_minutes')),
                    ['user' => $this->model->getKey(), 'hash' => sha1((string) $this->model->email)],
                ),
            ],
        );
    }
}
