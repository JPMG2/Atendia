<?php

declare(strict_types=1);

use App\Actions\Business\SyncOfferKnowledge;
use App\Ai\Agents\ActivityIntentDesigner;
use App\Ai\Agents\ConversationAnalyst;
use App\Enums\MessageDirection;
use App\Jobs\EmbedCatalog;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\QuestionIntent;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Database\Seeders\QuestionIntentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stage 2 — topics for N trades without anyone writing them
|--------------------------------------------------------------------------
| Universal intents + the trade's own (generated once, shared) + what one
| business proposes (shared once its peers see it too). The subject links
| to the business's own catalog by meaning.
*/

/**
 * Meaning stand-in: texts sharing a concept word land on the same axis,
 * so similarity is 1 between them and 0 with everything else.
 */
function meaningOf(string $text): array
{
    $concepts = ['estacion' => 1, 'tiroid' => 2, 'envio' => 3, 'mascota' => 4];
    $axis = 500 + (crc32($text) % 900);

    foreach ($concepts as $word => $conceptAxis) {
        if (str_contains(mb_strtolower(Str::ascii($text)), $word)) {
            $axis = $conceptAxis;
        }
    }

    $vector = array_fill(0, 1536, 0.0);
    $vector[$axis] = 1.0;

    return $vector;
}

beforeEach(function (): void {
    $this->seed(QuestionIntentSeeder::class);

    $embedder = $this->mock(KnowledgeEmbedder::class);
    $embedder->shouldReceive('embed')->andReturnUsing(fn (array $texts): array => array_map(meaningOf(...), $texts))->byDefault();
    $embedder->shouldReceive('embedOne')->andReturnUsing(fn (string $text): array => meaningOf($text))->byDefault();

    $this->lab = BusinessActivity::factory()->create(['code' => 'laboratorio', 'name' => 'Laboratorio']);
});

function labBusiness(BusinessActivity $activity): Business
{
    $business = Business::factory()->create();
    $business->activities()->attach($activity->id, ['is_primary' => true]);

    return $business;
}

function finishedThread(Business $business, string $question): Conversation
{
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'last_message_at' => now()->subHours(3)]);
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In, 'body' => $question]);

    return $thread;
}

function analystSays(string $question, string $intent = '', string $newIntent = '', string $subject = ''): array
{
    return ['questions' => [[
        'message' => 1, 'question' => $question, 'intent' => $intent, 'subject' => $subject, 'resolved_by' => 'nobody',
        'new_intent' => $newIntent, 'new_intent_description' => $newIntent !== '' ? 'Lo que el cliente quiere saber sobre esto' : '',
    ]], 'sentiment' => 'neutral'];
}

test('a trade gets its own intents once, shared by every business in it', function (): void {
    ActivityIntentDesigner::fake([['intents' => [['name' => 'Preparación para el estudio', 'description' => 'Ayuno y cuidados previos']]]]);
    ConversationAnalyst::fake([analystSays('¿Hay que ir en ayunas?', 'laboratorio.preparacion_para_el_estudio'), analystSays('¿Hay que ir en ayunas?', 'laboratorio.preparacion_para_el_estudio')]);

    $first = labBusiness($this->lab);
    $second = labBusiness($this->lab);
    finishedThread($first, '¿Hay que ir en ayunas?');
    finishedThread($second, '¿Hay que ir en ayunas?');

    $this->artisan('atendia:analyze-conversations');

    ActivityIntentDesigner::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Rubro: Laboratorio'));
    expect(QuestionIntent::query()->where('business_activity_id', $this->lab->id)->count())->toBe(1)
        ->and($this->lab->fresh()->intents_generated_at)->not->toBeNull()
        ->and(QuestionIntent::menuFor($second))->toHaveKey('laboratorio.preparacion_para_el_estudio')
        ->and(QuestionIntent::menuFor(Business::factory()->create()))->not->toHaveKey('laboratorio.preparacion_para_el_estudio')
        ->and(QuestionIntent::menuFor(tap(Business::factory()->create(), fn (Business $bakery) => $bakery->activities()->attach($this->lab->id, ['is_primary' => false]))))
        ->toHaveKey('laboratorio.preparacion_para_el_estudio')
        ->and(app(Tenant::class)->for(null, fn () => ConversationQuestion::query()->whereNotNull('question_intent_id')->count()))->toBe(2);
});

test('a dead trade designer never blocks the analysis', function (): void {
    ActivityIntentDesigner::fake(fn () => throw new RuntimeException('model down'));
    ConversationAnalyst::fake([analystSays('¿A qué hora abren?', 'hours')]);

    $thread = finishedThread(labBusiness($this->lab), '¿A qué hora abren?');

    $this->artisan('atendia:analyze-conversations');

    expect($thread->fresh()->analyzed_message_id)->not->toBeNull()
        ->and($this->lab->fresh()->intents_generated_at)->toBeNull();
});

test('what no intent fits becomes a proposal, and peers converge on it until it is the trade\'s', function (): void {
    config()->set('atendia.analysis.promote_after_businesses', 2);
    ActivityIntentDesigner::fake([['intents' => []]]);
    ConversationAnalyst::fake([
        analystSays('¿Puedo ir con mi perro?', newIntent: 'Mascotas permitidas'),
        analystSays('¿Aceptan mascotas?', newIntent: 'Ingreso con mascotas'),
    ]);

    $first = labBusiness($this->lab);
    finishedThread($first, '¿Puedo ir con mi perro?');
    $this->artisan('atendia:analyze-conversations');

    $proposal = QuestionIntent::query()->where('name', 'Mascotas permitidas')->sole();
    expect($proposal->proposed_by_business_id)->toBe($first->id)
        ->and($proposal->business_activity_id)->toBe($this->lab->id);

    // Another lab words it differently: same meaning, same topic — now shared.
    finishedThread(labBusiness($this->lab), '¿Aceptan mascotas?');
    $this->artisan('atendia:analyze-conversations');

    expect(QuestionIntent::query()->where('name', 'ilike', '%mascota%')->count())->toBe(1)
        ->and($proposal->fresh()->proposed_by_business_id)->toBeNull();
});

test('a proposal that means a universal intent reuses it instead of forking the topic', function (): void {
    ActivityIntentDesigner::fake([['intents' => []]]);
    // "Ubicación y cómo llegar" already covers parking in its description.
    ConversationAnalyst::fake([analystSays('¿Tienen estacionamiento?', newIntent: 'Estacionamiento para clientes')]);

    $business = labBusiness($this->lab);
    finishedThread($business, '¿Tienen estacionamiento?');
    $this->artisan('atendia:analyze-conversations');

    $question = app(Tenant::class)->for($business->id, fn () => ConversationQuestion::query()->with('intent')->sole());

    expect($question->intent->key)->toBe('location')
        ->and(QuestionIntent::query()->whereNotNull('proposed_by_business_id')->count())->toBe(0);
});

test('the subject links to the business catalog by meaning, or stays as not offered', function (): void {
    ActivityIntentDesigner::fake([['intents' => []]]);
    ConversationAnalyst::fake([
        analystSays('¿Cuánto sale el perfil tiroideo?', 'price', subject: 'Tiroideo'),
        analystSays('¿Hacen envíos a domicilio?', 'delivery', subject: 'Envío a domicilio'),
    ]);

    $business = labBusiness($this->lab);
    $thyroid = Service::factory()->create(['business_id' => $business->id, 'name' => 'Perfil tiroideo']);
    (new EmbedCatalog($business->id))->handle(app(KnowledgeEmbedder::class));

    finishedThread($business, '¿cuánto el tiroideo?');
    finishedThread($business, '¿hacen envíos?');
    $this->artisan('atendia:analyze-conversations');

    $questions = app(Tenant::class)->for($business->id, fn () => ConversationQuestion::query()->orderBy('id')->get());

    expect($questions[0]->service_id)->toBe($thyroid->id)
        ->and($questions[1]->service_id)->toBeNull()
        ->and($questions[1]->product_id)->toBeNull()
        ->and($questions[1]->subject)->toBe('Envío a domicilio');
});

test('a renamed catalog item drops its stale vector and queues the refill', function (): void {
    $business = Business::factory()->create();
    $service = Service::factory()->create(['business_id' => $business->id, 'name' => 'Perfil tiroideo']);

    (new EmbedCatalog($business->id))->handle(app(KnowledgeEmbedder::class));
    expect($service->fresh()->embedding)->not->toBeNull();

    Queue::fake();
    $service->fresh()->update(['name' => 'Perfil tiroideo completo']);

    expect($service->fresh()->embedding)->toBeNull();

    // Every catalog change syncs the offer, and the sync queues the refill.
    app(SyncOfferKnowledge::class)->handle($business, 'services');
    Queue::assertPushed(EmbedCatalog::class, fn (EmbedCatalog $job): bool => $job->businessId === $business->id);
});
