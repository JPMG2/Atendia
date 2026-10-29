<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Moderation\RecordModerationFlag;
use App\Enums\ModerationSeverity;
use App\Models\KnowledgeDocument;
use App\Services\ContentModeration;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Every text a business writes (profile, offer, taught answers, imports)
 * lands in a knowledge document, so screening the document screens it all.
 * Already saved, so a flagged text is not refused: severe suspends, the rest reaches the admin.
 */
class ModerateKnowledgeDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $documentId) {}

    public function handle(ContentModeration $moderation, RecordModerationFlag $record): void
    {
        $owner = KnowledgeDocument::ownerOf($this->documentId);

        if ($owner === null) {
            return;
        }

        // Adopted BEFORE reading: without a tenant the document of a landing example is not there.
        app(Tenant::class)->for($owner, function () use ($moderation, $record): void {
            $document = KnowledgeDocument::query()->with('business')->find($this->documentId);

            if ($document === null || $document->business === null) {
                return;
            }

            $verdict = $moderation->text($document->title."\n".$document->content);

            // Unchecked is not clean: the queue retries until moderation answers.
            if ($verdict->severity === ModerationSeverity::Unavailable) {
                throw new RuntimeException("Moderation unavailable for knowledge document {$document->id}.");
            }

            $record->handle($document->business, (string) $document->source_type, 'text', $verdict, (string) $document->content_hash);
        });
    }
}
