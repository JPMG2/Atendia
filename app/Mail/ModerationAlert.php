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

/** The admin hears of every new catch, severe or not: the refused ones are hers to review. */
class ModerationAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('moderation.alert.subject', [
            'business' => $this->model->loadMissing('business')->business?->name,
            'severity' => __('moderation.severity.'.$this->model->severity->value),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.moderation.alert', text: 'emails.moderation.alert-text');
    }
}
