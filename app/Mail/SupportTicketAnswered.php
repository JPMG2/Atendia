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

/**
 * Our answer by mail: where it goes when the business has no WhatsApp number to
 * receive it on. The code is in the subject so the person can quote it back.
 */
class SupportTicketAnswered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('support.mail.answered_subject', ['code' => $this->model->code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.answered',
            text: 'emails.support.answered-text',
        );
    }
}
