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

/** "Forgot my password" in the house voice, replacing Laravel's stock notification. */
class AccountPasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $token = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.password_reset.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.password-reset',
            text: 'emails.account.password-reset-text',
            with: [
                'resetUrl' => route('password.reset', ['token' => $this->token, 'email' => $this->model->email]),
                'minutes' => config('auth.passwords.users.expire'),
            ],
        );
    }
}
