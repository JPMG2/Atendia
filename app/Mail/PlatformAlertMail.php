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
 * A critical fact on the admin's inbox, besides her phone: the WhatsApp line can
 * be the very thing that is down, and an alert that rides only on it never arrives.
 */
class PlatformAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform_events.mail.subject', ['kind' => $this->model->kind->label()]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.alert', text: 'emails.platform.alert-text');
    }
}
