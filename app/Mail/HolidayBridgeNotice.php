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

/** Tells a business that a one-year day off (a bridge, or a day a decree moved) counts in its country, so its schedule is no surprise. */
class HolidayBridgeNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $dates  One day each, oldest first.
     * @param  bool  $bridge  Every day is a bridge; otherwise the mail says "feriado" and not "feriado puente".
     */
    public function __construct(public Business $business, public array $dates, public bool $bridge = true) {}

    /** Where the subject and body words live: the rest of the mail is the same for both. */
    public static function wordsFor(bool $bridge): string
    {
        return $bridge ? 'catalog.country_holiday.bridge.mail' : 'catalog.country_holiday.decree.mail';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans_choice(self::wordsFor($this->bridge).'.subject', count($this->dates)));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.holiday-bridge', text: 'emails.platform.holiday-bridge-text');
    }
}
