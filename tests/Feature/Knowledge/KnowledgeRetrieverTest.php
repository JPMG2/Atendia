<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

/**
 * A 1-hot vector of the configured dimensionality (non-zero, so cosine distance is defined).
 *
 * @return list<float>
 */
function unitVector(int $index): array
{
    $vector = array_fill(0, (int) config('rag.embedding.dimensions'), 0.0);
    $vector[$index] = 1.0;

    return $vector;
}

test('retrieval is scoped to a single company and never leaks another tenant', function (): void {
    Queue::fake();

    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    $docA = KnowledgeDocument::factory()->for($businessA)->create();
    $docB = KnowledgeDocument::factory()->for($businessB)->create();

    // Business A: two chunks pointing in different directions.
    KnowledgeChunk::factory()->forDocument($docA)->withEmbedding(unitVector(0))->create(['content' => 'A-cero']);
    KnowledgeChunk::factory()->forDocument($docA)->withEmbedding(unitVector(1))->create(['content' => 'A-uno']);
    // Business B: a chunk IDENTICAL to the query — it must never appear in A's results.
    KnowledgeChunk::factory()->forDocument($docB)->withEmbedding(unitVector(0))->create(['content' => 'B-cero']);

    // The query embeds to unitVector(0): globally the closest is B-cero, but we scope to A.
    Embeddings::fake(fn () => [unitVector(0)]);

    $results = app(KnowledgeRetriever::class)->retrieve('lo que sea', $businessA->id);

    expect($results)->toHaveCount(2)
        ->and($results->pluck('content')->all())->not->toContain('B-cero') // tenant isolation
        ->and($results->first()->content)->toBe('A-cero')                  // nearest within the tenant
        ->and($results->first()->distance)->toBeLessThan(0.01);            // identical direction ⇒ ~0 distance
});

test('the context block cites the source document title', function (): void {
    Queue::fake();

    $business = Business::factory()->create();
    $document = KnowledgeDocument::factory()->for($business)->create(['title' => 'Cómo conectar WhatsApp']);
    KnowledgeChunk::factory()->forDocument($document)->withEmbedding(unitVector(0))->create([
        'content' => 'Escaneá el código QR desde Configuración.',
    ]);

    Embeddings::fake(fn () => [unitVector(0)]);

    $context = app(KnowledgeRetriever::class)->context('cómo conecto whatsapp', $business->id);

    expect($context)->toContain('[Cómo conectar WhatsApp]')
        ->and($context)->toContain('Escaneá el código QR');
});
