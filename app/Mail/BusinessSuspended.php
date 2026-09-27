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

/** Tells the team why their assistant stopped: the reply to this mail is the appeal path. */
class BusinessSuspended extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('moderation.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.moderation.suspended', text: 'emails.moderation.suspended-text');
    }
}
