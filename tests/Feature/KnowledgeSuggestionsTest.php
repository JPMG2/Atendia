<?php

declare(strict_types=1);

use App\Actions\Business\SendHumanReply;
use App\Ai\Agents\ConversationAnalyst;
use App\Ai\Agents\QuestionMatcher;
use App\Enums\MessageDirection;
use App\Enums\SuggestionStatus;
use App\Jobs\NotifyUnansweredCustomers;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Database\Seeders\QuestionIntentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stage 3 — one queue of what the assistant did not know
|--------------------------------------------------------------------------
| Every question the team answered or nobody did folds into ONE suggestion
| per distinct question. The vector settles the obvious, the matcher the
| gray zone; the team's answer is the draft.
*/

/**
 * Vectors with a chosen similarity to the first axis: 1.0 is the same
 * text, 0.8 the gray zone, 0 unrelated.
 *
 * @param  array<string, float>  $closeness  text => similarity to the base
 */
function embedderWith(array $closeness): void
{
    $vector = function (string $text) use ($closeness): array {
        $similarity = $closeness[$text] ?? 0.0;
        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = $similarity;
        $vector[1 + (crc32($text) % 1500)] = sqrt(1 - $similarity ** 2);

        return $vector;
    };

    test()->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embed')
        ->andReturnUsing(fn (array $texts): array => array_map($vector, $texts));
}

function askedAndAnswered(Business $business, string $question, string $resolvedBy = 'team', string $answer = ''): array
{
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'last_message_at' => now()->subHours(3)]);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In, 'body' => '¿'.$question.'?']);

    return ['questions' => [[
        'message' => 1, 'question' => $question, 'intent' => 'hours', 'subject' => '', 'resolved_by' => $resolvedBy,
        'answer' => $answer, 'new_intent' => '', 'new_intent_description' => '',
    ]], 'sentiment' => 'neutral'];
}

function suggestionsOf(Business $business)
{
    return app(Tenant::class)->for($business->id, fn () => KnowledgeSuggestion::query()->queue()->get());
}

beforeEach(function (): void {
    $this->seed(QuestionIntentSeeder::class);
    $this->business = Business::factory()->create();
});

test('a question the team answered becomes a pending suggestion with its answer as the draft', function (): void {
    embedderWith(['¿Abren los sábados?' => 1.0]);
    ConversationAnalyst::fake([askedAndAnswered($this->business, '¿Abren los sábados?', answer: 'Sí, los sábados de 9 a 12.')]);
    QuestionMatcher::fake()->preventStrayPrompts();

    $this->artisan('atendia:analyze-conversations');

    $suggestion = suggestionsOf($this->business)->sole();

    expect($suggestion->question)->toBe('¿Abren los sábados?')
        ->and($suggestion->intent->name)->not->toBeEmpty()
        ->and($suggestion->asked_count)->toBe(1)
        ->and($suggestion->teamAnswer->answer)->toBe('Sí, los sábados de 9 a 12.');
});

test('what the assistant answered never reaches the queue, and an answer only sticks when the team gave it', function (): void {
    embedderWith([]);
    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿A qué hora abren?', 'assistant', 'De 8 a 12.'),
        askedAndAnswered($this->business, '¿Tienen estacionamiento?', 'nobody', 'Inventado'),
    ]);
    QuestionMatcher::fake()->preventStrayPrompts();

    $this->artisan('atendia:analyze-conversations');

    $questions = app(Tenant::class)->for($this->business->id, fn () => ConversationQuestion::query()->orderBy('id')->get());

    expect($questions[0]->knowledge_suggestion_id)->toBeNull()
        ->and($questions[1]->answer)->toBeNull()
        ->and(suggestionsOf($this->business)->pluck('question')->all())->toBe(['¿Tienen estacionamiento?']);
});

test('a near-identical asking folds into the suggestion without asking the matcher', function (): void {
    embedderWith(['¿Abren los sábados?' => 1.0, '¿Atienden los sábados?' => 0.97]);
    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿Abren los sábados?'),
        askedAndAnswered($this->business, '¿Atienden los sábados?'),
    ]);
    QuestionMatcher::fake()->preventStrayPrompts();

    $this->artisan('atendia:analyze-conversations');

    expect(suggestionsOf($this->business)->sole()->asked_count)->toBe(2);
    QuestionMatcher::assertNeverPrompted();
});

test('in the gray zone the matcher decides: same question folds, a different one stays apart', function (): void {
    embedderWith(['¿Aceptan tarjeta?' => 1.0, '¿Puedo pagar con tarjeta?' => 0.8, '¿Aceptan transferencia?' => 0.8]);
    ConversationAnalyst::fake([askedAndAnswered($this->business, '¿Aceptan tarjeta?')]);
    QuestionMatcher::fake()->preventStrayPrompts();
    $this->artisan('atendia:analyze-conversations');

    $card = suggestionsOf($this->business)->sole();

    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿Puedo pagar con tarjeta?'),
        askedAndAnswered($this->business, '¿Aceptan transferencia?'),
    ]);
    QuestionMatcher::fake(fn (string $prompt): array => ['matches' => [[
        'question' => 1,
        'suggestion' => str_contains($prompt, 'pagar con tarjeta') ? $card->id : 0,
        'same_as' => 0,
    ]]]);
    $this->artisan('atendia:analyze-conversations');

    expect(suggestionsOf($this->business)->mapWithKeys(fn (KnowledgeSuggestion $suggestion): array => [$suggestion->question => $suggestion->asked_count])->all())
        ->toBe(['¿Aceptan tarjeta?' => 2, '¿Aceptan transferencia?' => 1]);
});

test('a failure after teaching reopens the suggestion, a dismissed one absorbs it quietly', function (): void {
    embedderWith(['¿Abren los sábados?' => 1.0, '¿Tienen estacionamiento?' => 0.0]);
    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿Abren los sábados?'),
        askedAndAnswered($this->business, '¿Tienen estacionamiento?'),
    ]);
    QuestionMatcher::fake()->preventStrayPrompts();
    $this->artisan('atendia:analyze-conversations');

    [$taught, $dismissed] = app(Tenant::class)->for($this->business->id, function (): array {
        $taught = KnowledgeSuggestion::query()->where('question', '¿Abren los sábados?')->sole();
        $taught->markTaught(KnowledgeDocument::factory()->create(['business_id' => $this->business->id, 'source_type' => 'faq']));
        $dismissed = KnowledgeSuggestion::query()->where('question', '¿Tienen estacionamiento?')->sole();
        $dismissed->dismiss();

        return [$taught, $dismissed];
    });

    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿Abren los sábados?', 'nobody'),
        askedAndAnswered($this->business, '¿Tienen estacionamiento?', 'nobody'),
    ]);
    $this->artisan('atendia:analyze-conversations');

    expect($taught->fresh()->status)->toBe(SuggestionStatus::Pending)
        ->and($taught->fresh()->knowledge_document_id)->not->toBeNull()
        ->and($dismissed->fresh()->status)->toBe(SuggestionStatus::Dismissed)
        ->and(app(Tenant::class)->for(null, fn () => KnowledgeSuggestion::query()->count()))->toBe(2);
});

test('a dead matcher leaves the questions unlinked and the next sweep picks them up', function (): void {
    embedderWith(['¿Aceptan tarjeta?' => 1.0, '¿Puedo pagar con tarjeta?' => 0.8]);
    ConversationAnalyst::fake([
        askedAndAnswered($this->business, '¿Aceptan tarjeta?'),
        askedAndAnswered($this->business, '¿Puedo pagar con tarjeta?'),
    ]);
    QuestionMatcher::fake(fn () => throw new RuntimeException('model down'));
    $this->artisan('atendia:analyze-conversations');

    expect(app(Tenant::class)->for(null, fn () => KnowledgeSuggestion::query()->count()))->toBe(1);

    QuestionMatcher::fake([['matches' => [['question' => 1, 'suggestion' => suggestionsOf($this->business)->sole()->id, 'same_as' => 0]]]]);
    $this->artisan('atendia:analyze-conversations');

    expect(suggestionsOf($this->business)->sole()->asked_count)->toBe(2);
});

test('another business\'s suggestions are never a candidate', function (): void {
    embedderWith(['¿Abren los sábados?' => 1.0]);
    $other = Business::factory()->create();
    ConversationAnalyst::fake([
        askedAndAnswered($other, '¿Abren los sábados?'),
        askedAndAnswered($this->business, '¿Abren los sábados?'),
    ]);
    QuestionMatcher::fake()->preventStrayPrompts();

    $this->artisan('atendia:analyze-conversations');

    expect(suggestionsOf($this->business))->toHaveCount(1)
        ->and(suggestionsOf($other))->toHaveCount(1)
        ->and(suggestionsOf($this->business)->sole()->id)->not->toBe(suggestionsOf($other)->sole()->id);
});

test('the taught answer reaches only the quiet threads left without one, once', function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
    Queue::fake();

    $business = Business::factory()->create(['whatsapp_instance' => 'atendia-demo', 'whatsapp_connected_at' => now()]);
    $thread = fn (string $phone, int $hoursQuiet): Conversation => Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => $phone, 'last_message_at' => now()->subHours($hoursQuiet)]);

    $suggestion = KnowledgeSuggestion::factory()
        ->askedIn($stranded = $thread('5491100000001', 3))
        ->askedIn($thread('5491100000002', 3), teamAnswer: 'Sí.')
        ->askedIn($thread('5491100000003', 0))
        ->create(['business_id' => $business->id, 'question' => '¿Abren los sábados?']);

    $faq = KnowledgeDocument::factory()->create(['business_id' => $business->id, 'source_type' => 'faq', 'content' => "Pregunta: ¿Abren los sábados?\nRespuesta: Sí, de 8 a 12."]);
    $suggestion->markTaught($faq);

    expect($suggestion->notifiableCustomers())->toBe(1);

    (new NotifyUnansweredCustomers($business->id, $suggestion->id))->handle(app(SendHumanReply::class));
    (new NotifyUnansweredCustomers($business->id, $suggestion->id))->handle(app(SendHumanReply::class));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['number'] === '5491100000001'
        && str_contains((string) $request['text'], '«¿Abren los sábados?»')
        && str_contains((string) $request['text'], 'Sí, de 8 a 12.')
        && ! str_contains((string) $request['text'], 'Pregunta:'));

    expect($stranded->messages()->latest('id')->first()->body)->toContain('Sí, de 8 a 12.')
        ->and($suggestion->notifiableCustomers())->toBe(0);
});
