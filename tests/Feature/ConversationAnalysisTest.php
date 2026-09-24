<?php

declare(strict_types=1);

use App\Ai\Agents\ConversationAnalyst;
use App\Enums\ConversationStatus;
use App\Enums\CustomerSentiment;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\QuestionIntentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stage 1 — the finished thread is read whole, once
|--------------------------------------------------------------------------
| Each real question comes out rewritten to stand on its own (semantic)
| with who solved it (contextual). A reopened thread only adds its new
| stretch; small talk alone never costs a call.
*/

beforeEach(function (): void {
    $this->seed(QuestionIntentSeeder::class);

    $this->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embed')
        ->andReturnUsing(fn (array $texts): array => array_map(fn (): array => array_fill(0, 1536, 0.001), $texts))
        ->byDefault();

    $this->business = Business::factory()->create();
});

function threadSaying(Business $business, array $turns, array $attributes = []): Conversation
{
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'last_message_at' => now()->subHours(3), ...$attributes]);

    foreach ($turns as [$direction, $body]) {
        ConversationMessage::factory()->create([
            'business_id' => $business->id,
            'conversation_id' => $thread->id,
            'direction' => $direction === 'in' ? MessageDirection::In : MessageDirection::Out,
            'author' => match ($direction) {
                'ai' => MessageAuthor::Assistant,
                'team' => MessageAuthor::Human,
                default => null,
            },
            'body' => $body,
        ]);
    }

    return $thread;
}

test('a quiet thread is analyzed into standalone questions with who solved them', function (): void {
    ConversationAnalyst::fake([[
        'questions' => [
            ['message' => 1, 'question' => '¿A qué hora abren?', 'intent' => 'hours', 'subject' => '', 'resolved_by' => 'assistant'],
            ['message' => 3, 'question' => '¿Abren los sábados?', 'intent' => 'hours', 'subject' => '', 'resolved_by' => 'team'],
        ],
        'sentiment' => 'positive',
    ]]);

    $thread = threadSaying($this->business, [
        ['in', '¿A qué hora abren?'],
        ['ai', 'De 8 a 12.'],
        ['in', '¿y los sábados?'],
        ['team', 'Los sábados también, de 9 a 11.'],
    ]);

    $this->artisan('atendia:analyze-conversations')->assertSuccessful();

    $questions = ConversationQuestion::query()->with(['intent', 'message'])->orderBy('id')->get();
    $saturday = $questions->last();

    expect($questions)->toHaveCount(2)
        ->and($saturday->question)->toBe('¿Abren los sábados?')
        ->and($saturday->intent->key)->toBe('hours')
        ->and($saturday->resolved_by)->toBe(QuestionResolution::Team)
        ->and($saturday->message->body)->toBe('¿y los sábados?')
        ->and($saturday->embedding)->not->toBeNull()
        ->and($saturday->subject)->toBeNull()
        ->and($thread->analyses()->sole()->sentiment)->toBe(CustomerSentiment::Positive)
        ->and($thread->fresh()->analyzed_message_id)->toBe($thread->messages()->max('id'));

    // It read the whole stretch, the team's answer included.
    ConversationAnalyst::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '[3] Cliente: ¿y los sábados?')
        && str_contains($prompt->prompt, '[4] Equipo: Los sábados también'));
});

test('only finished threads are analyzed: resolved, or quiet for the idle window', function (): void {
    ConversationAnalyst::fake([['questions' => [], 'sentiment' => 'neutral']]);

    $resolved = threadSaying($this->business, [['in', '¿Hacen envíos?']], ['status' => ConversationStatus::Resolved, 'last_message_at' => now()]);
    $ongoing = threadSaying($this->business, [['in', '¿Hacen envíos?']], ['last_message_at' => now()->subMinutes(30)]);
    $waitingForTeam = threadSaying($this->business, [['in', '¿Hacen envíos?']], ['status' => ConversationStatus::Team]);

    $this->artisan('atendia:analyze-conversations')->assertSuccessful();

    expect($resolved->fresh()->analyzed_message_id)->not->toBeNull()
        ->and($ongoing->fresh()->analyzed_message_id)->toBeNull()
        ->and($waitingForTeam->fresh()->analyzed_message_id)->toBeNull();
});

test('a reopened thread analyzes only its new stretch, the old one as context', function (): void {
    ConversationAnalyst::fake([
        ['questions' => [['message' => 1, 'question' => '¿A qué hora abren?', 'intent' => 'hours', 'subject' => '', 'resolved_by' => 'assistant']], 'sentiment' => 'neutral'],
        ['questions' => [['message' => 1, 'question' => '¿Cuánto sale el perfil tiroideo?', 'intent' => 'price', 'subject' => 'Perfil tiroideo', 'resolved_by' => 'nobody']], 'sentiment' => 'negative'],
    ]);

    $thread = threadSaying($this->business, [['in', '¿A qué hora abren?'], ['ai', 'De 8 a 12.']]);
    $this->artisan('atendia:analyze-conversations');

    ConversationMessage::factory()->create(['business_id' => $this->business->id, 'conversation_id' => $thread->id, 'body' => '¿cuánto sale el tiroideo?']);
    $this->artisan('atendia:analyze-conversations');

    expect(ConversationQuestion::query()->pluck('question')->all())->toBe(['¿A qué hora abren?', '¿Cuánto sale el perfil tiroideo?'])
        ->and(ConversationQuestion::query()->latest('id')->first()->subject)->toBe('Perfil tiroideo')
        ->and($thread->analyses()->count())->toBe(2);

    ConversationAnalyst::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, "Contexto previo (ya analizado):\nCliente: ¿A qué hora abren?")
        && str_contains($prompt->prompt, '[1] Cliente: ¿cuánto sale el tiroideo?'));
});

test('small talk alone never costs an ai call but still advances the thread', function (): void {
    ConversationAnalyst::fake();

    $thread = threadSaying($this->business, [['in', 'Ok, muchas gracias 🙌'], ['ai', '¡A vos!']]);

    $this->artisan('atendia:analyze-conversations');

    ConversationAnalyst::assertNeverPrompted();
    expect($thread->fresh()->analyzed_message_id)->toBe($thread->messages()->max('id'));
});

test('a question anchored to a message outside the stretch is dropped', function (): void {
    ConversationAnalyst::fake([['questions' => [
        ['message' => 2, 'question' => 'Inventada sobre la respuesta', 'intent' => 'hours', 'subject' => '', 'resolved_by' => 'assistant'],
        ['message' => 9, 'question' => 'Fuera de rango', 'intent' => 'nope', 'subject' => '', 'resolved_by' => 'assistant'],
    ], 'sentiment' => 'neutral']]);

    threadSaying($this->business, [['in', '¿Abren hoy?'], ['ai', 'Sí, de 8 a 12.']]);

    $this->artisan('atendia:analyze-conversations');

    expect(ConversationQuestion::query()->count())->toBe(0);
});

test('a dead model leaves the thread pending so the next run retries it', function (): void {
    ConversationAnalyst::fake(fn () => throw new RuntimeException('model down'));

    $thread = threadSaying($this->business, [['in', '¿Abren hoy?']]);

    $this->artisan('atendia:analyze-conversations');

    expect($thread->fresh()->analyzed_message_id)->toBeNull();
});

test('the backfill can be bounded to the last days', function (): void {
    ConversationAnalyst::fake([['questions' => [], 'sentiment' => 'neutral']]);

    $recent = threadSaying($this->business, [['in', '¿Abren hoy?']], ['last_message_at' => now()->subDays(10)]);
    $old = threadSaying($this->business, [['in', '¿Abren hoy?']], ['last_message_at' => now()->subDays(120)]);

    $this->artisan('atendia:analyze-conversations', ['--days' => 90]);

    expect($recent->fresh()->analyzed_message_id)->not->toBeNull()
        ->and($old->fresh()->analyzed_message_id)->toBeNull();
});
