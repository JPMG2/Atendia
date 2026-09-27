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

/** A suspended business asks for a second look: the admin reads why before deciding. */
class ModerationAppeal extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('moderation.appeal.mail_subject', ['business' => $this->model->name]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.moderation.appeal', text: 'emails.moderation.appeal-text');
    }
}
