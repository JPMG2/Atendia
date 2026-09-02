<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\SearchBusinessKnowledge;
use App\Models\Business;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The assistant wired to the business's knowledge
|--------------------------------------------------------------------------
| "¿Venden el alternador de un Fiat Palio 1.4?" — the assistant searches the
| business's imported knowledge and answers from what it finds, or says
| plainly that it could not confirm it. The tool is the wire.
*/

/** A 1-hot embedding of the configured dimensionality. */
function knowledgeVector(int $index): array
{
    $vector = array_fill(0, (int) config('rag.embedding.dimensions'), 0.0);
    $vector[$index] = 1.0;

    return $vector;
}

test('the assistant carries the search tool only while serving a business', function (): void {
    $business = Business::factory()->create();

    // With no business (AtendIa's own site) there is no knowledge to search.
    expect(iterator_to_array((new AsistenteAtendia)->tools()))->toBe([])
        ->and(iterator_to_array((new AsistenteAtendia($business))->tools())[0])
        ->toBeInstanceOf(SearchBusinessKnowledge::class);
});

test('the tool answers with the business knowledge, cited by source', function (): void {
    Queue::fake();

    $business = Business::factory()->create();
    $document = KnowledgeDocument::factory()->for($business)->create(['title' => 'Inventario 2026']);

    KnowledgeChunk::factory()->forDocument($document)->withEmbedding(knowledgeVector(0))->create([
        'content' => 'Alternador Bosch — Fiat Palio / Siena 1.4 8v — stock: 3',
    ]);

    Embeddings::fake(fn () => [knowledgeVector(0)]);

    $answer = (string) (new SearchBusinessKnowledge($business->id))
        ->handle(new Request(['query' => 'alternador fiat palio 1.4']));

    expect($answer)->toContain('Alternador Bosch')
        ->toContain('[Inventario 2026]');
});

test('the tool never reads another tenant, even with the answer sitting there', function (): void {
    Queue::fake();

    $mine = Business::factory()->create();
    $other = Business::factory()->create();

    $foreign = KnowledgeDocument::factory()->for($other)->create();
    KnowledgeChunk::factory()->forDocument($foreign)->withEmbedding(knowledgeVector(0))->create([
        'content' => 'Alternador Bosch — stock: 3',
    ]);

    Embeddings::fake(fn () => [knowledgeVector(0)]);

    // The business is pinned at construction: the model cannot point it away.
    $answer = (string) (new SearchBusinessKnowledge($mine->id))
        ->handle(new Request(['query' => 'alternador']));

    expect($answer)->not->toContain('Alternador Bosch');
});

test('with nothing relevant the tool says so, instead of handing back silence', function (): void {
    Queue::fake();

    $business = Business::factory()->create();

    Embeddings::fake(fn () => [knowledgeVector(0)]);

    // An empty string reads as "no context" and the model improvises; the
    // explicit sentence makes it answer "I could not confirm it".
    $answer = (string) (new SearchBusinessKnowledge($business->id))
        ->handle(new Request(['query' => 'algo que no existe']));

    expect($answer)->toContain('No se encontró información');
});

test('the instructions demand searching before claiming, and honesty when not found', function (): void {
    $instructions = (string) (new AsistenteAtendia)->instructions();

    expect($instructions)->toContain('buscá SIEMPRE primero')
        ->toContain('Nunca inventes');
});
