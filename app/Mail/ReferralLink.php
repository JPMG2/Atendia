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
 * "Gana con AtendIa" in the inbox — the Starlink pattern the owner asked
 * for: the referral link arrives by mail once, ready to be forwarded as-is.
 */
class ReferralLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The message knows WHAT to say; who reads it is the channel's business,
     * so only the model comes in. Public so the views read `$model` directly.
     */
    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.referral_link.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.referral.link',
            text: 'emails.referral.link-text',
        );
    }
}
