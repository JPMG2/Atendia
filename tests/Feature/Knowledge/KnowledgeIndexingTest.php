<?php

declare(strict_types=1);

use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

test('creating a document hashes the content, marks it pending and queues indexing', function (): void {
    Queue::fake();

    $document = KnowledgeDocument::factory()->create();

    expect($document->status)->toBe('pending')
        ->and($document->content_hash)->not->toBeNull();

    Queue::assertPushed(
        IndexKnowledgeDocument::class,
        fn (IndexKnowledgeDocument $job): bool => $job->documentId === $document->id,
    );
});

test('editing only the title does not re-queue indexing, but editing the content does', function (): void {
    Queue::fake();

    $document = KnowledgeDocument::factory()->create();
    Queue::assertPushed(IndexKnowledgeDocument::class, 1);

    $document->update(['title' => 'Otro título']);
    Queue::assertPushed(IndexKnowledgeDocument::class, 1); // unchanged

    $document->update(['content' => 'Contenido totalmente nuevo.']);
    Queue::assertPushed(IndexKnowledgeDocument::class, 2);
});

test('the indexer builds embedded chunks and marks the document indexed', function (): void {
    Queue::fake();       // do not auto-run the job on create
    Embeddings::fake();  // deterministic fake embeddings of the configured dimensions

    $document = KnowledgeDocument::factory()->create([
        'content' => trim(str_repeat('Contenido de prueba para indexar. ', 60)),
    ]);

    app(KnowledgeIndexer::class)->index($document->fresh());
    $document->refresh();

    expect($document->status)->toBe('indexed')
        ->and($document->indexed_at)->not->toBeNull()
        ->and($document->chunks()->count())->toBeGreaterThan(0);

    $chunk = $document->chunks()->first();

    expect($chunk->embedding)->toBeArray()
        ->and($chunk->embedding)->toHaveCount((int) config('rag.embedding.dimensions'))
        ->and($chunk->company_id)->toBe($document->company_id);
});

test('re-indexing replaces the previous chunks instead of duplicating them', function (): void {
    Queue::fake();
    Embeddings::fake();

    $document = KnowledgeDocument::factory()->create(['content' => 'Un fragmento corto.']);
    $indexer = app(KnowledgeIndexer::class);

    $indexer->index($document->fresh());
    $count = $document->chunks()->count();

    $indexer->index($document->fresh());

    expect($document->chunks()->count())->toBe($count);
});

test('the embedding column round-trips a float array via the native cast (no custom cast, no dependency)', function (): void {
    Queue::fake();

    $document = KnowledgeDocument::factory()->create();
    $dimensions = (int) config('rag.embedding.dimensions');
    $vector = array_map(static fn (int $i): float => round($i / 1000, 4), range(1, $dimensions));

    $chunk = KnowledgeChunk::factory()->forDocument($document)->withEmbedding($vector)->create();

    $fresh = KnowledgeChunk::findOrFail($chunk->id);

    expect($fresh->embedding)->toBeArray()
        ->and($fresh->embedding)->toHaveCount($dimensions)
        ->and($fresh->embedding[0])->toBeFloat();
});
