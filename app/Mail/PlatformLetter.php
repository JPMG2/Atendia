<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A letter from the platform team to one business, written with the admin's
 * assistant and sent only after she approved it. A person reads the replies.
 */
class PlatformLetter extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Business $business, public string $heading, public string $text) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.letter', text: 'emails.platform.letter-text');
    }
}
