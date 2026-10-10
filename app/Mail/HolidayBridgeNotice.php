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

/** Tells a business that a bridge day was marked for its country, so its schedule is no surprise. */
class HolidayBridgeNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param list<string> $dates One day per bridge, oldest first. */
    public function __construct(public Business $business, public array $dates) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans_choice('catalog.country_holiday.bridge.mail.subject', count($this->dates)));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform.holiday-bridge', text: 'emails.platform.holiday-bridge-text');
    }
}
