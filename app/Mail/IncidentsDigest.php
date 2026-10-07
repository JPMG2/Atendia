<?php

declare(strict_types=1);

namespace App\Mail;

use App\Dto\IncidentRowDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** What the product got wrong today, worst first. */
class IncidentsDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<IncidentRowDto>  $rows
     */
    public function __construct(
        public Model $model,
        public array $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans_choice('incidents.mail.subject', count($this->rows), ['count' => count($this->rows)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.digest',
            text: 'emails.incidents.digest-text',
            with: ['lines' => $this->lines()],
        );
    }

    /**
     * One line per incident, worst first, each naming the business and the
     * customer behind it. The mail is the nudge; the evidence is on the screen.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(fn (IncidentRowDto $row): string => __('incidents.mail.line', [
            'what' => __('incidents.kinds.'.$row->kind->value.'.label'),
            'business' => $row->business ?? __('incidents.no_business'),
            'customer' => $row->customer ?? '—',
            'waiting' => $row->minutesWaiting < 60
                ? trans_choice('incidents.waiting_minutes', $row->minutesWaiting, ['count' => $row->minutesWaiting])
                : trans_choice('incidents.waiting_hours', intdiv($row->minutesWaiting, 60), ['count' => intdiv($row->minutesWaiting, 60)]),
        ]), $this->rows);
    }
}
