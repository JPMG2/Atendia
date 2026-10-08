<?php

declare(strict_types=1);

use App\Actions\Embeddings\ActivateEmbeddingMigration;
use App\Actions\Embeddings\CancelEmbeddingMigration;
use App\Actions\Embeddings\DiscardEmbeddingBackup;
use App\Actions\Embeddings\ResumeEmbeddingMigration;
use App\Actions\Embeddings\RollBackEmbeddingMigration;
use App\Actions\Embeddings\StartEmbeddingMigration;
use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Enums\AiCapability;
use App\Enums\EmbeddingMigrationStatus;
use App\Models\AiModel;
use App\Models\EmbeddingMigration;
use App\Models\HelpArticle;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Knowledge\VectorColumns;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Changing the embedding model
|--------------------------------------------------------------------------
| Every vector in the base was made by one model, so the change converts
| beside the old, switches at once, and keeps the old until it is discarded.
| These tests pin each step against the real columns, not against a flag.
*/

beforeEach(function (): void {
    EmbeddingSpace::forget();
    Embeddings::fake();

    $this->newModel = AiModel::query()->create([
        'provider' => 'openai', 'capability' => AiCapability::Embedding, 'code' => 'embedding-nuevo', 'label' => 'Embedding nuevo',
        'prompt_per_million' => 0.13, 'dimensions' => 768, 'effective_from' => '2026-10-01', 'is_active' => true,
    ]);

    // Three chunks and one article hold vectors in the old space; the rest of the tables are empty.
    $this->chunks = KnowledgeChunk::factory()->count(3)->create();
    $this->article = HelpArticle::factory()->create();
    DB::table('help_articles')->where('id', $this->article->id)->update(['embedding' => json_encode(array_fill(0, (int) config('rag.embedding.dimensions'), 0.1))]);
});

afterEach(fn () => EmbeddingSpace::forget());

function startChange(): EmbeddingMigration
{
    return app(StartEmbeddingMigration::class)->handle('embedding-nuevo', 768);
}

function vectorLength(string $table, string $column): ?int
{
    $length = DB::table($table)->whereNotNull($column)->selectRaw("vector_dims({$column}) as length")->value('length');

    return $length === null ? null : (int) $length;
}

test('starting opens the new column on every table and converts beside the old, without touching the model in force', function (): void {
    $migration = startChange();

    foreach (EmbeddingTables::registered() as $table) {
        expect(Schema::hasColumn($table->table, 'embedding_next'))->toBeTrue();
    }

    // The new vectors have the new length; the live ones are as they were.
    expect(vectorLength('knowledge_chunks', 'embedding_next'))->toBe(768)
        ->and(vectorLength('knowledge_chunks', 'embedding'))->toBe((int) config('rag.embedding.dimensions'))
        ->and(DB::table('knowledge_chunks')->whereNull('embedding_next')->count())->toBe(0)
        ->and(vectorLength('help_articles', 'embedding_next'))->toBe(768)
        // The assistant still searches with the old model.
        ->and(EmbeddingSpace::active()->model)->toBe(config('rag.embedding.model'))
        ->and($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::Ready);
});

test('the new vectors are made with the NEW model, not the one in force', function (): void {
    startChange();

    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->model === 'embedding-nuevo' && $prompt->dimensions === 768);
});

test('only one change at a time, and never to the model already in force', function (): void {
    startChange();

    expect(fn () => startChange())->toThrow(DomainException::class, 'already_open');

    app(CancelEmbeddingMigration::class)->handle(EmbeddingMigration::open());

    AiModel::query()->create([
        'provider' => 'openai', 'capability' => AiCapability::Embedding, 'code' => config('rag.embedding.model'), 'label' => 'Actual',
        'prompt_per_million' => 0.02, 'dimensions' => (int) config('rag.embedding.dimensions'), 'effective_from' => '2026-10-01', 'is_active' => true,
    ]);

    expect(fn () => app(StartEmbeddingMigration::class)->handle(config('rag.embedding.model'), (int) config('rag.embedding.dimensions')))
        ->toThrow(DomainException::class, 'same_as_active')
        ->and(fn () => app(StartEmbeddingMigration::class)->handle('no-existe', 512))
        ->toThrow(DomainException::class, 'unknown_model');
});

test('switching puts the new model in force on every table at once and keeps the old vectors aside', function (): void {
    $migration = startChange();

    app(ActivateEmbeddingMigration::class)->handle($migration);

    expect(EmbeddingSpace::active()->model)->toBe('embedding-nuevo')
        ->and(EmbeddingSpace::active()->dimensions)->toBe(768)
        // Every query keeps reading `embedding`, and now it holds the new space.
        ->and(vectorLength('knowledge_chunks', 'embedding'))->toBe(768)
        ->and(vectorLength('help_articles', 'embedding'))->toBe(768)
        ->and(vectorLength('knowledge_chunks', 'embedding_prev'))->toBe((int) config('rag.embedding.dimensions'))
        ->and(Schema::hasColumn('knowledge_chunks', 'embedding_next'))->toBeFalse()
        ->and($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::Switched);
});

test('the index follows the column, so the next change finds its name free', function (): void {
    $migration = startChange();
    app(ActivateEmbeddingMigration::class)->handle($migration);

    $names = collect(DB::select("select indexname from pg_indexes where tablename = 'knowledge_chunks' and indexdef ilike '%hnsw%'"))->pluck('indexname')->all();

    expect($names)->toContain('knowledge_chunks_embedding_vectorindex', 'knowledge_chunks_embedding_prev_vectorindex')
        ->not->toContain('knowledge_chunks_embedding_next_vectorindex');

    app(DiscardEmbeddingBackup::class)->handle($migration->refresh());

    // The whole cycle can run again: nothing is left holding a name.
    AiModel::query()->create([
        'provider' => 'openai', 'capability' => AiCapability::Embedding, 'code' => 'embedding-otro', 'label' => 'Otro',
        'prompt_per_million' => 0.02, 'dimensions' => 512, 'effective_from' => '2026-10-01', 'is_active' => true,
    ]);

    expect(fn () => app(StartEmbeddingMigration::class)->handle('embedding-otro', 512))->not->toThrow(Throwable::class)
        ->and(vectorLength('knowledge_chunks', 'embedding_next'))->toBe(512);
});

test('a row written while it waited blocks the switch until it is converted', function (): void {
    $migration = startChange();

    // Written after the last conversion: it has a vector in the old space and none in the new.
    KnowledgeChunk::factory()->create();

    expect(fn () => app(ActivateEmbeddingMigration::class)->handle($migration->refresh()))
        ->toThrow(DomainException::class, 'not_converted');

    // Creating the chunk also indexes its document, so "what is pending" is read from the base.
    $unconverted = DB::table('knowledge_chunks')->whereNull('embedding_next')->count();

    expect($unconverted)->toBeGreaterThan(0)
        ->and(EmbeddingSpace::active()->model)->toBe(config('rag.embedding.model'))
        ->and(app(ResumeEmbeddingMigration::class)->handle($migration->refresh()))->toBe($unconverted)
        ->and($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::Ready);

    app(ActivateEmbeddingMigration::class)->handle($migration);

    expect(EmbeddingSpace::active()->model)->toBe('embedding-nuevo');
});

test('a chunk written after the switch needs no vector in the old column', function (): void {
    $migration = startChange();
    app(ActivateEmbeddingMigration::class)->handle($migration);

    // The old column was NOT NULL; left that way, every new chunk would be refused.
    KnowledgeChunk::factory()->state(['embedding' => null])->create();

    expect(DB::table('knowledge_chunks')->whereNull('embedding')->count())->toBe(1);
});

test('going back restores the old vectors, drops the new ones and gives a vector to what was written since', function (): void {
    $migration = startChange();
    app(ActivateEmbeddingMigration::class)->handle($migration);

    // Written under the new model: it has no vector in the old space.
    KnowledgeChunk::factory()->state(['embedding' => null])->create();

    app(RollBackEmbeddingMigration::class)->handle($migration->refresh());

    expect(EmbeddingSpace::active()->model)->toBe(config('rag.embedding.model'))
        ->and(Schema::hasColumn('knowledge_chunks', 'embedding_next'))->toBeFalse()
        ->and(Schema::hasColumn('knowledge_chunks', 'embedding_prev'))->toBeFalse()
        ->and($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::RolledBack)
        // Everything is back in the old space, the late chunk included.
        ->and(DB::table('knowledge_chunks')->whereNull('embedding')->count())->toBe(0)
        ->and(DB::table('knowledge_chunks')->selectRaw('count(distinct vector_dims(embedding)) as lengths')->value('lengths'))->toBe(1)
        ->and(vectorLength('knowledge_chunks', 'embedding'))->toBe((int) config('rag.embedding.dimensions'));
});

test('discarding drops the old vectors for good and closes the change', function (): void {
    $migration = startChange();
    app(ActivateEmbeddingMigration::class)->handle($migration);

    app(DiscardEmbeddingBackup::class)->handle($migration->refresh());

    expect(Schema::hasColumn('knowledge_chunks', 'embedding_prev'))->toBeFalse()
        ->and($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::Finished)
        ->and(EmbeddingMigration::open())->toBeNull()
        // And the model stays the new one: Finished still rules.
        ->and(EmbeddingSpace::active()->model)->toBe('embedding-nuevo')
        ->and(fn () => app(RollBackEmbeddingMigration::class)->handle($migration))->toThrow(DomainException::class, 'not_rollbackable');
});

test('cancelling before the switch leaves everything as it was', function (): void {
    $migration = startChange();

    app(CancelEmbeddingMigration::class)->handle($migration);

    foreach (EmbeddingTables::registered() as $table) {
        expect(Schema::hasColumn($table->table, 'embedding_next'))->toBeFalse();
    }

    expect($migration->refresh()->status)->toBe(EmbeddingMigrationStatus::Cancelled)
        ->and(vectorLength('knowledge_chunks', 'embedding'))->toBe((int) config('rag.embedding.dimensions'))
        ->and(EmbeddingSpace::active()->model)->toBe(config('rag.embedding.model'))
        // Once switched there is no cancelling: that is going back.
        ->and(fn () => app(CancelEmbeddingMigration::class)->handle($migration))->toThrow(DomainException::class, 'not_cancellable');
});

test('the embedder follows the model in force', function (): void {
    $migration = startChange();
    app(ActivateEmbeddingMigration::class)->handle($migration);

    app(KnowledgeEmbedder::class)->embed(['una pregunta']);

    // An index built over the new length is what lets the retriever keep working.
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('una pregunta') && $prompt->model === 'embedding-nuevo' && $prompt->dimensions === 768);
});

test('the progress is read from the base, table by table', function (): void {
    $columns = app(VectorColumns::class);
    $chunks = EmbeddingTables::named('knowledge_chunks');

    $inBase = DB::table('knowledge_chunks')->whereNotNull('embedding')->count();

    expect($columns->total($chunks))->toBe($inBase)
        ->and($inBase)->toBeGreaterThanOrEqual(3);

    startChange();

    expect($columns->converted($chunks))->toBe($inBase)
        ->and($columns->pending($chunks))->toBe(0);
});

test('a client never reaches the screen, and the screen does not act without the key', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');

    $this->actingAs($client)->get('/admin/ia')->assertForbidden();

    Livewire::actingAs($client)->test('ai.embedding-space')->call('choose')->assertForbidden();
});

test('the screen walks the whole change with its confirmations and says each step', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $screen = Livewire::actingAs($admin)->test('ai.embedding-space')
        ->assertSee(config('rag.embedding.model'))
        ->assertSee('Cambiar el modelo…')
        ->call('choose')
        ->set('form.target', 'embedding-nuevo|768')
        ->call('start')
        ->assertDispatched('notify')
        ->assertSee('Listo para activar')
        ->assertSee('Activar el modelo nuevo')
        ->assertDontSee('Cambiar el modelo…');

    $screen->call('activate')
        ->assertSee('Activo, con el anterior guardado')
        ->assertSee('Volver al anterior')
        ->assertSee('Descartar el anterior')
        ->call('discard')
        ->assertSee('Terminado')
        ->assertSee('Cambiar el modelo…');

    expect(EmbeddingSpace::active()->model)->toBe('embedding-nuevo');
});

test('a model that is not in the catalog cannot be chosen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    Livewire::actingAs($admin)->test('ai.embedding-space')
        ->call('choose')
        ->set('form.target', 'cualquiera|1536')
        ->call('start')
        ->assertHasErrors('target');

    expect(EmbeddingMigration::open())->toBeNull();
});

test('the cost of the new vectors is billed at the price of the model that made them', function (): void {
    $book = AiModel::priceBook();

    expect($book->get('embedding-nuevo')->first()->prompt_per_million)->toBe('0.1300')
        ->and($book->get('embedding-nuevo')->first()->dimensions)->toBe(768);
});
