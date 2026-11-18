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
 * Security receipt for the lost-phone pause, both ways. The resume one is
 * the one that matters: if she did not lift it, the reset link is right there.
 */
class LostPhonePauseChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public bool $paused,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.lost_phone.'.$this->key().'.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.lost-phone',
            text: 'emails.account.lost-phone-text',
            with: ['key' => 'mail.account.lost_phone.'.$this->key(), 'time' => now()->translatedFormat('d \d\e F \d\e Y, H:i')],
        );
    }

    private function key(): string
    {
        return $this->paused ? 'paused' : 'resumed';
    }
}
