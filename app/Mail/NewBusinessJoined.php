<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Somebody signed up, told to the one person who wants to call them. */
class NewBusinessJoined extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public Business $business,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('platform.new_business.subject', ['business' => $this->business->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform.new-business',
            text: 'emails.platform.new-business-text',
            with: ['business' => $this->business],
        );
    }
}
