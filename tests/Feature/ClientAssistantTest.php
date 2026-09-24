<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\FaqDrafter;
use App\Enums\QuestionResolution;
use App\Enums\SuggestionStatus;
use App\Jobs\IndexKnowledgeDocument;
use App\Jobs\NotifyUnansweredCustomers;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\QuestionIntent;
use App\Models\User;
use App\Services\Knowledge\KnowledgeRetriever;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();
});

/**
 * A queued suggestion asked $asked times, each in its own thread; the
 * team's answer, when given, is the draft.
 */
function queuedSuggestion(User $user, string $question, string $intentKey, int $asked = 1, ?string $teamAnswer = null): KnowledgeSuggestion
{
    $intent = QuestionIntent::query()->firstOrCreate(['key' => $intentKey], ['name' => ucfirst($intentKey), 'description' => '', 'sort_order' => 0]);
    $suggestion = KnowledgeSuggestion::query()->create(['business_id' => $user->business_id, 'question_intent_id' => $intent->id, 'question' => $question]);

    foreach (range(1, $asked) as $ignored) {
        $thread = Conversation::factory()->create(['business_id' => $user->business_id]);
        $analysis = ConversationAnalysis::query()->create(['business_id' => $user->business_id, 'conversation_id' => $thread->id, 'first_message_id' => 1, 'last_message_id' => 1, 'sentiment' => 'neutral']);

        ConversationQuestion::query()->create([
            'business_id' => $user->business_id,
            'conversation_id' => $thread->id,
            'conversation_analysis_id' => $analysis->id,
            'question_intent_id' => $intent->id,
            'question' => $question,
            'resolved_by' => $teamAnswer !== null ? QuestionResolution::Team : QuestionResolution::Nobody,
            'answer' => $teamAnswer,
            'knowledge_suggestion_id' => $suggestion->id,
        ]);
    }

    return $suggestion;
}

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

test('the queue groups by topic, ranks by asks and shows the team answer as the draft', function (): void {
    $user = assistantClient();
    queuedSuggestion($user, '¿Hacen envíos?', 'delivery');
    queuedSuggestion($user, '¿Aceptan Visa?', 'payment', asked: 2, teamAnswer: 'Sí, Visa y Mastercard.');
    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee(__('client.assistant.suggestions_title'))
        ->assertSeeInOrder(['¿Aceptan Visa?', '¿Hacen envíos?'])
        ->assertSee(__('client.assistant.draft_from_team'))
        ->assertSee('Sí, Visa y Mastercard.')
        ->assertSee(__('client.assistant.no_answer_yet'));
});

test('approving the team draft teaches it and takes it off the queue', function (): void {
    $user = assistantClient();
    $suggestion = queuedSuggestion($user, '¿Aceptan Visa?', 'payment', teamAnswer: 'Sí, Visa y Mastercard.');
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('approve', $suggestion->id)
        ->assertSet('sheetOpen', false)
        ->assertDontSee(__('client.assistant.suggestions_title'));

    $faq = KnowledgeDocument::query()->where('source_type', 'faq')->sole();

    expect($faq->title)->toBe('¿Aceptan Visa?')
        ->and($faq->content)->toContain('Sí, Visa y Mastercard.')
        ->and($faq->conversation_id)->toBe($suggestion->latestQuestion->conversation_id)
        ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Taught)
        ->and($suggestion->fresh()->knowledge_document_id)->toBe($faq->id);
});

test('teaching without a draft opens the sheet on the rewritten question', function (): void {
    $user = assistantClient();
    $suggestion = queuedSuggestion($user, '¿Hacen envíos a domicilio?', 'delivery');
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('teachSuggestion', $suggestion->id)
        ->assertSet('sheetOpen', true)
        ->assertSet('form.question', '¿Hacen envíos a domicilio?')
        ->assertSet('form.answer', '')
        ->set('form.answer', 'Sí, en toda la ciudad.')
        ->call('saveFaq');

    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::Taught);
});

test('dismissing takes it off the queue for good', function (): void {
    $user = assistantClient();
    $suggestion = queuedSuggestion($user, '¿Venden bebidas?', 'products');
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('dismissSuggestion', $suggestion->id)
        ->assertDontSee(__('client.assistant.suggestions_title'));

    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::Dismissed)
        ->and(KnowledgeDocument::query()->count())->toBe(0);
});

test('suggesting drafts answers only what near-miss knowledge can ground', function (): void {
    $user = assistantClient();
    $visa = queuedSuggestion($user, '¿Aceptan Visa?', 'payment');
    queuedSuggestion($user, '¿Hacen resonancias?', 'services');
    $this->actingAs($user);

    // Visa has a near-miss fragment; resonancias has nothing to stand on.
    $this->mock(KnowledgeRetriever::class)
        ->shouldReceive('context')
        ->andReturnUsing(fn (string $query): string => str_contains($query, 'Visa')
            ? '[Medios de pago] Se aceptan tarjetas Visa y Mastercard.'
            : '');

    FaqDrafter::fake([['answer' => 'Sí, aceptamos Visa y Mastercard.']]);

    livewire('assistant.index')
        ->call('suggest')
        ->assertSee('Sí, aceptamos Visa y Mastercard.')
        ->assertSee(__('client.assistant.draft_from_ai'))
        ->call('teachSuggestion', $visa->id)
        ->assertSet('sheetOpen', true)
        ->assertSet('form.question', '¿Aceptan Visa?')
        ->assertSet('form.answer', 'Sí, aceptamos Visa y Mastercard.');
});

test('with nothing to ground on the sweep says so instead of inventing', function (): void {
    $user = assistantClient();
    queuedSuggestion($user, '¿Hacen resonancias?', 'services');
    $this->actingAs($user);

    $this->mock(KnowledgeRetriever::class)
        ->shouldReceive('context')
        ->andReturn('');

    livewire('assistant.index')
        ->call('suggest')
        ->assertSet('drafts', []);
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

test('a queued question links to the thread it was last asked in', function (): void {
    $user = assistantClient();
    $suggestion = queuedSuggestion($user, '¿Hacen envíos?', 'delivery', asked: 2);
    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee(__('client.assistant.view_thread'))
        ->assertSeeHtml('?hilo='.$suggestion->latestQuestion->conversation_id);
});

test('another tenant\'s suggestion is out of reach even by id', function (): void {
    $user = assistantClient();
    $foreign = queuedSuggestion(assistantClient(), '¿Aceptan Visa?', 'payment', teamAnswer: 'Sí.');
    $this->actingAs($user);

    livewire('assistant.index')
        ->call('approve', $foreign->id)
        ->call('dismissSuggestion', $foreign->id)
        ->assertSet('sheetOpen', false);

    expect($foreign->fresh()->status)->toBe(SuggestionStatus::Pending)
        ->and(KnowledgeDocument::query()->count())->toBe(0);
});

test('a taught answer shows how many replies it grounded', function (): void {
    Queue::fake();

    $user = assistantClient();
    $faq = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Hacen envíos?',
    ]);

    $thread = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->out()->count(2)->for($thread)->create([
        'business_id' => $user->business_id,
        'knowledge_sources' => [['id' => $faq->id, 'title' => $faq->title]],
    ]);

    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee(trans_choice('client.assistant.times_used', 2, ['count' => 2]));
});

test('an answer never cited shows no usage tally', function (): void {
    Queue::fake();

    $user = assistantClient();
    KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Hacen envíos?',
    ]);

    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee('¿Hacen envíos?')
        ->assertDontSee(trans_choice('client.assistant.times_used', 1, ['count' => 1]));
});

test('the team drafts are approved in one go, and a failing one stays queued', function (): void {
    $user = assistantClient();
    $visa = queuedSuggestion($user, '¿Aceptan Visa?', 'payment', teamAnswer: 'Sí, Visa y Mastercard.');
    $hours = queuedSuggestion($user, '¿Abren los sábados?', 'hours', teamAnswer: 'Sí, de 8 a 12.');
    $short = queuedSuggestion($user, '¿Y?', 'hours', teamAnswer: 'Sí.');
    $untouched = queuedSuggestion($user, '¿Hacen envíos?', 'delivery');
    $this->actingAs($user);

    livewire('assistant.index')
        ->assertSee('Aprobar las 3 de tu equipo')
        ->call('approveTeamDrafts')
        ->assertSet('lastTaughtId', null);

    expect($visa->fresh()->status)->toBe(SuggestionStatus::Taught)
        ->and($hours->fresh()->status)->toBe(SuggestionStatus::Taught)
        ->and($short->fresh()->status)->toBe(SuggestionStatus::Pending)
        ->and($untouched->fresh()->status)->toBe(SuggestionStatus::Pending)
        ->and(KnowledgeDocument::query()->where('source_type', 'faq')->count())->toBe(2);
});

test('after teaching, the banner offers the try once indexed and to tell who asked', function (): void {
    Queue::fake();
    $user = assistantClient();
    $suggestion = queuedSuggestion($user, '¿Aceptan Visa?', 'payment', asked: 3, teamAnswer: 'Sí, Visa y Mastercard.');
    // The team answered the first asking; the other two customers got nothing.
    $suggestion->questions()->whereKeyNot($suggestion->questions()->min('id'))->update(['resolved_by' => QuestionResolution::Nobody, 'answer' => null]);
    Conversation::query()->update(['last_message_at' => now()->subHours(3)]);
    $this->actingAs($user);

    $page = livewire('assistant.index')
        ->call('approve', $suggestion->id)
        ->assertSet('lastTaughtId', $suggestion->id)
        ->assertSee(__('client.assistant.learning'))
        ->assertSee('Avisarles a los 2 clientes que preguntaron');

    KnowledgeDocument::query()->update(['indexed_at' => now()]);

    $page->call('$refresh')
        ->assertSee(__('client.assistant.try'))
        ->call('notifyCustomers')
        ->assertSet('customersNotified', true)
        ->assertDontSee('Avisarles a los 2 clientes que preguntaron');

    Queue::assertPushed(NotifyUnansweredCustomers::class, fn (NotifyUnansweredCustomers $job): bool => $job->suggestionId === $suggestion->id);
});
