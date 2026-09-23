<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The admin's verdict on a receipt: credited (with the new period) or rejected (with why). */
class BillingPaymentReviewed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->copy().'.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing.reviewed',
            text: 'emails.billing.reviewed-text',
            with: ['copy' => $this->copy()],
        );
    }

    private function copy(): string
    {
        return $this->model->status === PaymentStatus::Paid ? 'mail.billing.paid' : 'mail.billing.rejected';
    }
}
