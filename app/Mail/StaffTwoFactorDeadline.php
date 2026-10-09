<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a person of the team that their plazo to turn the second step on ends tomorrow. */
class StaffTwoFactorDeadline extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model, public CarbonImmutable $deadline) {}

    public function envelope(): Envelope
    {
        // "Mañana" only when it is true: the owner's button can remind someone with days to spare.
        return new Envelope(subject: $this->deadline->isTomorrow()
            ? __('mail.staff_two_factor_deadline.subject')
            : __('mail.staff_two_factor_deadline.subject_soon', ['date' => $this->deadline->format('d/m/Y')]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.two-factor-deadline',
            text: 'emails.account.two-factor-deadline-text',
        );
    }
}
