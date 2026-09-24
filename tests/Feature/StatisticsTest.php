<?php

declare(strict_types=1);

use App\Classes\Main\Statistics;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeSuggestion;
use App\Models\QuestionIntent;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\QuestionIntentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Creating a knowledge document queues its indexing (embeddings API).
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(QuestionIntentSeeder::class);
});

function statsClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

/**
 * One customer question as the conversation analysis leaves it, in its own
 * thread with one inbound message.
 */
function analyzedQuestion(User $user, string $question, string $intentKey, string $resolvedBy = 'assistant', ?string $subject = null, ?int $serviceId = null): ConversationQuestion
{
    $thread = Conversation::factory()->create(['business_id' => $user->business_id]);
    $message = ConversationMessage::factory()->create(['business_id' => $user->business_id, 'conversation_id' => $thread->id, 'body' => $question]);
    $analysis = ConversationAnalysis::query()->create(['business_id' => $user->business_id, 'conversation_id' => $thread->id, 'first_message_id' => $message->id, 'last_message_id' => $message->id, 'sentiment' => 'neutral']);

    return ConversationQuestion::query()->create([
        'business_id' => $user->business_id,
        'conversation_id' => $thread->id,
        'conversation_analysis_id' => $analysis->id,
        'conversation_message_id' => $message->id,
        'question_intent_id' => QuestionIntent::query()->where('key', $intentKey)->value('id'),
        'question' => $question,
        'subject' => $subject,
        'service_id' => $serviceId,
        'resolved_by' => $resolvedBy,
    ]);
}

test('a guest is sent to login', function (): void {
    $this->get('/estadisticas')->assertRedirect('/login');
});

test('the floor plan sees the counts and every deeper block padlocked, never hidden', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.kpis.conversations'))
        ->assertSee(__('statistics.locked_in', ['plan' => __('plan.names.negocio')]))
        ->assertSee(__('statistics.locked_in', ['plan' => __('plan.names.premium')]))
        ->assertSee(__('statistics.see_plans'));
});

test('the patterns level shows the topics table with its reading and the fix per topic', function (): void {
    $user = statsClient();
    analyzedQuestion($user, '¿Cuánto cuesta el perfil tiroideo?', 'price');
    analyzedQuestion($user, '¿Cuánto sale el hemograma?', 'price', 'nobody');
    $pending = analyzedQuestion($user, '¿Abren los sábados?', 'hours', 'nobody');
    $pending->update(['knowledge_suggestion_id' => KnowledgeSuggestion::factory()->create(['business_id' => $user->business_id])->id]);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.topics.title'))
        ->assertSee(__('statistics.topics.insight', ['topic' => 'Precios y presupuestos', 'share' => 50]))
        ->assertSeeInOrder(['Precios y presupuestos', '2 consultas', '50%', 'Horarios', '1 consulta', '0%', __('statistics.topics.teach')])
        ->assertSee(__('statistics.daily.title'))
        ->assertDontSee(__('statistics.locked_in', ['plan' => __('plan.names.negocio')]));
});

test('a failed question about something not in the catalog asks to add it, and feeds the premium gaps', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);
    $cleaning = Service::factory()->create(['business_id' => $user->business_id, 'name' => 'Limpieza dental']);

    analyzedQuestion($user, '¿Hacen blanqueamiento dental?', 'service_info', 'nobody', 'Blanqueamiento dental');
    analyzedQuestion($user, 'Quiero un blanqueamiento', 'service_info', 'nobody', 'blanqueamiento dental');
    analyzedQuestion($user, '¿Cuánto sale la limpieza?', 'price', 'assistant', 'Limpieza dental', $cleaning->id);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.topics.add_catalog'))
        ->assertSee(trans_choice('statistics.gaps.line', 2, ['count' => 2, 'sample' => 'Blanqueamiento dental']))
        ->assertDontSee('«Limpieza dental»', false);
});

test('with an empty catalog nothing reads as missing from it', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);
    analyzedQuestion($user, '¿Hacen blanqueamiento?', 'service_info', 'nobody', 'Blanqueamiento');

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.gaps.none'))
        ->assertDontSee(__('statistics.topics.add_catalog'));
});

test('the month kpis count questions and resolve them one by one, not per thread', function (): void {
    $user = statsClient();
    // One thread, four questions: three solved by the assistant is 75%, not a failed thread.
    $first = analyzedQuestion($user, '¿Precios?', 'price');
    foreach (['assistant', 'assistant', 'nobody'] as $resolvedBy) {
        ConversationQuestion::query()->create([...$first->only(['business_id', 'conversation_id', 'conversation_analysis_id', 'question_intent_id']), 'question' => '¿Algo más?', 'resolved_by' => $resolvedBy]);
    }

    $stats = new Statistics($user->business);

    expect($stats->monthKpis())->toMatchArray(['conversations' => 1, 'questions' => 4, 'resolution' => 75])
        ->and($stats->sinceDayOne['questions'])->toBe(4)
        ->and($stats->peakHours['window'])->not->toBeNull();
});

test('what counts as a real question', function (string $text, bool $enquiry): void {
    expect(ConversationMessage::looksLikeEnquiry($text))->toBe($enquiry);
})->with([
    'yes' => ['Si', false],
    'no' => ['No', false],
    'thanks' => ['Ok, muchas gracias 🙌', false],
    'greeting' => ['Buenas tardes', false],
    'laugh' => ['jajaja', false],
    'emoji only' => ['👍', false],
    'one-word question' => ['Horarios?', true],
    'open today' => ['¿Hoy abren?', true],
    'greeting plus question' => ['Hola, quería saber el precio', true],
    'yes plus question' => ['Sí, pero ¿hacen domicilio?', true],
]);

test('each topic compares with last month and unfolds into the real questions', function (): void {
    $user = statsClient();
    $this->travel(-1)->months();
    analyzedQuestion($user, '¿Cuánto sale el hemograma?', 'price');
    analyzedQuestion($user, '¿Precio del perfil?', 'price');
    $this->travelBack();

    analyzedQuestion($user, '¿Cuánto cuesta la glucemia?', 'price');
    analyzedQuestion($user, '¿Y el colesterol?', 'price', 'nobody');
    analyzedQuestion($user, '¿Precio de la orina?', 'price', 'team');
    analyzedQuestion($user, '¿Abren los sábados?', 'hours');

    $topics = collect((new Statistics($user->business))->topics)->keyBy('topic');

    expect($topics['Precios y presupuestos']['delta'])->toBe(50)
        ->and($topics['Horarios']['delta'])->toBeNull()
        ->and(array_column($topics['Precios y presupuestos']['samples'], 'question'))
        ->toBe(['¿Precio de la orina?', '¿Y el colesterol?', '¿Cuánto cuesta la glucemia?']);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee('↑ 50%')
        ->assertSee('¿Y el colesterol?')
        ->assertSee(__('statistics.topics.by.nobody'));
});

test('a customer who wrote back after the late answer counts as recovered this month', function (): void {
    $user = statsClient();
    $wroteBack = analyzedQuestion($user, '¿Aceptan Visa?', 'payment', 'nobody');
    $silent = analyzedQuestion($user, '¿Aceptan Visa?', 'payment', 'nobody');
    $wroteBack->update(['customer_notified_at' => now()->subHour()]);
    $silent->update(['customer_notified_at' => now()->subHour()]);
    // The original questions came before the late answer; only a reply after it counts.
    ConversationMessage::query()->update(['created_at' => now()->subHours(2)]);
    ConversationMessage::factory()->create(['business_id' => $user->business_id, 'conversation_id' => $wroteBack->conversation_id, 'body' => '¡Gracias!']);

    expect((new Statistics($user->business))->monthKpis()['recovered'])->toBe(1);

    $this->actingAs($user);

    livewire('statistics.index')->assertSee(__('statistics.kpis.recovered'));
});
