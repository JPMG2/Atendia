<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The six-digit code that opens the door when the account signs in from an
 * unknown device: whoever holds the inbox is the owner. The code travels in
 * clear here and nowhere else — the session only keeps its hash.
 */
class DeviceChallengeCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.challenge.subject', ['code' => $this->code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security.challenge-code',
            text: 'emails.security.challenge-code-text',
        );
    }
}
