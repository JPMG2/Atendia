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

/** A new report, with the code in the subject so a reply can quote it. */
class SupportTicketOpened extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('support.mail.subject', [
                'code' => $this->model->code,
                'business' => $this->model->business?->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.opened',
            text: 'emails.support.opened-text',
        );
    }
}
