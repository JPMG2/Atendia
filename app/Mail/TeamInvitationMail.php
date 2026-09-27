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

/** The seat offered by the owner: one button to create a password and join. */
class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.team.invitation.subject', ['business' => $this->model->business->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team.invitation',
            text: 'emails.team.invitation-text',
            with: [
                'business' => $this->model->business->name,
                'joinUrl' => route('team.join', ['token' => $this->token]),
                'days' => (int) config('atendia.team.invitation_days'),
            ],
        );
    }
}
