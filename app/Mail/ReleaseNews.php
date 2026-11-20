<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** What a new version brings, for the owner who does not open the panel that week. */
class ReleaseNews extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{version: string, title: string, items: list<string>}  $release
     */
    public function __construct(
        public Model $model,
        public array $release,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.release_news.subject', ['brand' => Company::brand(), 'version' => $this->release['version']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.release.news',
            text: 'emails.release.news-text',
        );
    }
}
