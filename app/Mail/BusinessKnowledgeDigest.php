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

/** The Monday recap of what the assistant learned and what it could not answer. */
class BusinessKnowledgeDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{count: int, top: ?string}  $missed
     */
    public function __construct(
        public Model $model,
        public int $taught,
        public array $missed,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.knowledge_digest.subject', ['business' => $this->model->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest.knowledge',
            text: 'emails.digest.knowledge-text',
        );
    }
}
