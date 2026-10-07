<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A business that was trying is now paying: the only conversion that counts. */
class FirstPaymentReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('platform.first_payment.subject', ['business' => (string) $this->model->business?->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform.first-payment',
            text: 'emails.platform.first-payment-text',
            with: ['payment' => $this->model],
        );
    }
}
