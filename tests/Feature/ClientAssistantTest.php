<?php

declare(strict_types=1);

use App\Jobs\IndexKnowledgeDocument;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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
