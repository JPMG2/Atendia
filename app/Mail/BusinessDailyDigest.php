<?php

declare(strict_types=1);

namespace App\Mail;

use App\Dto\DailyDigestDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The owner's end-of-day read: what the assistant handled and what is next. */
class BusinessDailyDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public DailyDigestDto $digest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.daily_digest.subject', ['business' => $this->digest->business]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest.daily',
            text: 'emails.digest.daily-text',
        );
    }
}
