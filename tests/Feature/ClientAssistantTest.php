<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\SearchBusinessKnowledge;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeMiss;
use App\Models\User;
use App\Services\Knowledge\KnowledgeRetriever;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();
});

function assistantClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

test('guests are sent to the login', function (): void {
    $this->get(route('assistant'))->assertRedirect(route('login'));
});

test('the automatic sources show their real state', function (): void {
    $user = assistantClient();
    KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'services',
        'indexed_at' => now(),
    ]);
    $this->actingAs($user);

    $this->get(route('assistant'))
        ->assertSuccessful()
        ->assertSee(__('client.assistant.sources.services'))
        ->assertSee(__('client.assistant.sources.profile'))
        ->assertSee(__('client.assistant.source_empty'));
});

test('teaching a question stores it and queues the embedding into pgvector', function (): void {
    $user = assistantClient();
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('add')
        ->set('form.question', '¿Necesito ayuno para el perfil lipídico?')
        ->set('form.answer', 'Sí, 12 horas de ayuno.')
        ->call('saveFaq')
        ->assertSet('sheetOpen', false);

    $faq = KnowledgeDocument::query()->where('source_type', 'faq')->sole();

    expect($faq->title)->toBe('¿Necesito ayuno para el perfil lipídico?')
        ->and($faq->content)->toContain('Pregunta: ¿Necesito ayuno')
        ->and($faq->content)->toContain('Respuesta: Sí, 12 horas de ayuno.');

    Queue::assertPushed(IndexKnowledgeDocument::class);
});

test('editing loads the bare answer and re-saves without doubling the prefix', function (): void {
    $user = assistantClient();
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('add')
        ->set('form.question', '¿Aceptan tarjeta?')
        ->set('form.answer', 'Sí, débito y crédito.')
        ->call('saveFaq');

    $faq = KnowledgeDocument::query()->where('source_type', 'faq')->sole();

    livewire('assistant.index')
        ->call('edit', $faq->id)
        ->assertSet('form.answer', 'Sí, débito y crédito.')
        ->set('form.answer', 'Sí, y también transferencia.')
        ->call('saveFaq');

    expect($faq->refresh()->content)
        ->toBe("Pregunta: ¿Aceptan tarjeta?\nRespuesta: Sí, y también transferencia.");
});

test('a short question never reaches the knowledge base', function (): void {
    $user = assistantClient();
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('add')
        ->set('form.question', '¿?')
        ->set('form.answer', 'Algo.')
        ->call('saveFaq')
        ->assertHasErrors(['question']);

    expect(KnowledgeDocument::query()->where('source_type', 'faq')->count())->toBe(0);
});

test('deleting a taught answer makes the assistant forget it', function (): void {
    $user = assistantClient();
    $faq = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Hacen envíos?',
    ]);
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('deleteFaq', $faq->id);

    expect(KnowledgeDocument::query()->whereKey($faq->id)->exists())->toBeFalse();
});

test('an empty knowledge search logs the miss at the source', function (): void {
    $business = Business::factory()->create();

    $this->mock(KnowledgeRetriever::class)
        ->shouldReceive('context')
        ->andReturn('');

    $tool = new SearchBusinessKnowledge($business->id);
    $reply = (string) $tool->handle(new Request(['query' => '¿Hacen resonancias?']));

    expect($reply)->toContain('No se encontró')
        ->and(KnowledgeMiss::query()->sole()->query)->toBe('¿Hacen resonancias?');
});

test('the unanswered queue ranks by frequency, accent-blind, and prefills the sheet', function (): void {
    $user = assistantClient();

    foreach (['¿Aceptan Visa?', 'aceptan visa', '¿Hacen envíos?'] as $query) {
        KnowledgeMiss::factory()->create(['business_id' => $user->business_id, 'query' => $query]);
    }

    $this->actingAs($user);

    // The freshest phrasing represents its group: "aceptan visa" (2 asks)
    // outranks the single "¿Hacen envíos?".
    livewire('assistant.index')
        ->assertSee(__('client.assistant.misses_title'))
        ->assertSeeInOrder(['aceptan visa', '¿Hacen envíos?'])
        ->call('teach', 'aceptan visa')
        ->assertSet('sheetOpen', true)
        ->assertSet('form.question', 'aceptan visa');
});

test('trying a taught answer runs the REAL assistant and shows the reply', function (): void {
    $user = assistantClient();
    $faq = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Aceptan tarjeta?',
        'indexed_at' => now(),
    ]);
    $this->actingAs($user);

    AsistenteAtendia::fake(['Buscando…', 'Sí, aceptamos débito y crédito.']);

    livewire('assistant.index')
        ->assertSee(__('client.assistant.try'))
        ->call('tryNow', $faq->id)
        ->assertSee(__('client.assistant.try_title'))
        ->assertSee('Sí, aceptamos débito y crédito.');
});

test('an unindexed answer offers no try button yet', function (): void {
    $user = assistantClient();
    KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Hacen envíos?',
        'indexed_at' => null,
    ]);
    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee(__('client.assistant.learning'))
        ->assertDontSee(__('client.assistant.try_title'));
});

test('another tenant\'s taught answers are out of reach even by id', function (): void {
    $stranger = assistantClient();
    $foreign = KnowledgeDocument::factory()->create([
        'business_id' => $stranger->business_id,
        'source_type' => 'faq',
        'title' => 'Pregunta ajena',
    ]);

    $user = assistantClient();
    $this->actingAs($user);

    livewire('assistant.index')
        ->assertDontSee('Pregunta ajena')
        ->call('edit', $foreign->id)
        ->assertSet('sheetOpen', false)
        ->call('deleteFaq', $foreign->id);

    // Checked as its owner: the tenant scope hides it from anyone else.
    $this->actingAs($stranger);
    expect(KnowledgeDocument::query()->whereKey($foreign->id)->exists())->toBeTrue();
});
