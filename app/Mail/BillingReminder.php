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
 * "Your next payment is in N days" / "overdue, N grace days left" / "your
 * assistant is paused": one mail, three stages, same numbers as the panel.
 */
class BillingReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $stage = 'upcoming',
        public int $days = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("mail.billing.{$this->stage}.subject", ['days' => $this->days]),
        );
    }

    public function content(): Content
    {
        // loadMissing: the billing pass hands over businesses loaded in a batch,
        // where a lazy load is refused.
        $subscription = $this->model->loadMissing('subscription')->subscription;

        return new Content(
            view: 'emails.billing.reminder',
            text: 'emails.billing.reminder-text',
            with: [
                'copy' => "mail.billing.{$this->stage}",
                'amount' => config('atendia.billing.currency').' '.number_format((float) $subscription?->nextAmount(), 2, ',', '.'),
                'date' => $subscription?->periodEndsAt()?->setTimezone($this->model->localTimezone())->format('d/m/Y'),
                'plan' => __('plan.names.'.$subscription?->plan),
            ],
        );
    }
}
