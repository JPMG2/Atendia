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

/** The businesses whose AI ate more of their plan than that plan allows. */
class AiSpendAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, cost: string, share: int, limit: int}>  $rows
     */
    public function __construct(
        public Model $model,
        public array $rows,
        public string $month,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans_choice('admin.ai_usage.mail.subject', count($this->rows), ['count' => count($this->rows)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai.spend-alert',
            text: 'emails.ai.spend-alert-text',
            with: ['lines' => $this->lines()],
        );
    }

    /**
     * One line per business: what it cost, what share of its plan that is and
     * where its own threshold sits. The mail is the nudge; the figures behind
     * it live on the screen.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(fn (array $row): string => __('admin.ai_usage.mail.line', $row), $this->rows);
    }
}
