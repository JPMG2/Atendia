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
 * Tells the admin that the "most movement" ranking stopped being recomputed.
 * A stored figure that dies in silence keeps looking like a live one.
 */
class BusinessActivityStale extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  string  $since  The last time it was computed, already formatted. */
    public function __construct(
        public Model $model,
        public string $since,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('admin.home.activity.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.activity-stale', text: 'emails.platform.activity-stale-text');
    }
}
