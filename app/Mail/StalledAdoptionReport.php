<?php

declare(strict_types=1);

namespace App\Mail;

use App\Dto\AdoptionRowDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The accounts that went quiet before their assistant ever answered. */
class StalledAdoptionReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<AdoptionRowDto>  $rows
     */
    public function __construct(
        public Model $model,
        public array $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans_choice('adoption.mail.subject', count($this->rows), ['count' => count($this->rows)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.adoption.stalled',
            text: 'emails.adoption.stalled-text',
            with: ['lines' => $this->lines()],
        );
    }

    /**
     * One line per account: what it is, where it stopped and how long it has
     * been gone. The mail is the nudge; the detail lives on the screen.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(fn (AdoptionRowDto $row): string => __('adoption.mail.line', [
            'business' => $row->business ?? $row->email,
            'step' => $row->step->label(),
            'days' => trans_choice('adoption.mail.in_step', $row->daysInStep, ['count' => $row->daysInStep, 'limit' => $row->stallLimit]),
        ]), $this->rows);
    }
}
