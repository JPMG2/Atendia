<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\ConversationAnalyst;
use App\Ai\Agents\QuestionMatcher;
use App\Classes\Main\Statistics;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\SuggestionStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\QuestionIntentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A real day, end to end
|--------------------------------------------------------------------------
| Unit tests prove each piece; this walks the whole loop the way a client
| lives it, across two businesses in two countries, with the embedder
| going down once. Born from the 2026-09-24 audit: every bug it found sat
| BETWEEN pieces that were green on their own.
*/

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');
    Cache::flush();
    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(QuestionIntentSeeder::class);

    // The embedder is down for the first batch it gets (the analysis), then back.
    $this->embedderCalls = 0;
    $embedder = $this->mock(KnowledgeEmbedder::class);
    $embedder->shouldReceive('embedOne')->andReturn(array_fill(0, 1536, 0.01));
    $embedder->shouldReceive('embed')->andReturnUsing(function (array $texts): array {
        if (++$this->embedderCalls === 1) {
            throw new RuntimeException('embedder down');
        }

        return array_map(fn (): array => array_fill(0, 1536, 0.01), $texts);
    });
});

function customerWritesTo(string $instance, string $phone, string $name, string $text, string $id): void
{
    (new ProcessIncomingWhatsAppMessage($instance, $phone, $name, $text, $id, null, 0))->handle();
}

/** @return list<Request> */
function whatsappSentTo(string $phone): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): Request => $pair[0], Http::recorded()->all()),
        fn (Request $request): bool => str_contains($request->url(), '/message/sendText/') && $request['number'] === $phone,
    ));
}

test('a real day: asked, left unanswered, taught, answered late, and the customer comes back', function (): void {
    $lab = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'lab-vida',
        'whatsapp_connected_at' => now(),
        'timezone' => null,
        'country_id' => Country::factory()->create(['iso2' => 'VE'])->id,
    ]);
    $salon = Business::factory()->create(['name' => 'Peluquería Lumen', 'whatsapp_instance' => 'lumen', 'whatsapp_connected_at' => now()]);
    $owner = User::factory()->create(['business_id' => $lab->id]);
    $salonOwner = User::factory()->create(['business_id' => $salon->id]);

    // 09:30 in Caracas. Two customers, two businesses, one question each.
    $this->travelTo(CarbonImmutable::parse('2026-09-15 13:30:00', 'UTC'));
    AsistenteAtendia::fake(['No tengo ese dato.', 'No lo pude confirmar, te aviso.', 'Sí, hacemos alisado.', 'Sí, hacemos alisado con keratina.']);
    customerWritesTo('lab-vida', '584141111111', 'Ana', '¿Abren el sábado?', 'M1');
    customerWritesTo('lumen', '5491133333333', 'Sofía', '¿Hacen alisado?', 'M2');

    // Three quiet hours later the analysis reads both finished threads.
    $this->travel(3)->hours();
    ConversationAnalyst::fake(fn (string $prompt): array => str_contains($prompt, 'sábado')
        ? ['questions' => [['message' => 1, 'question' => '¿Abren los sábados?', 'intent' => 'hours', 'subject' => '', 'resolved_by' => 'nobody', 'answer' => '', 'new_intent' => '', 'new_intent_description' => '']], 'sentiment' => 'neutral']
        : ['questions' => [['message' => 1, 'question' => '¿Hacen alisado?', 'intent' => 'service_info', 'subject' => '', 'resolved_by' => 'assistant', 'answer' => '', 'new_intent' => '', 'new_intent_description' => '']], 'sentiment' => 'positive']);
    QuestionMatcher::fake()->preventStrayPrompts();

    $this->artisan('atendia:analyze-conversations')->assertSuccessful();

    // The lab's owner sees what her assistant did not know; the salon's does not.
    $this->actingAs($salonOwner);
    livewire('assistant.index')->assertDontSee('¿Abren los sábados?');

    $this->actingAs($owner);
    $suggestion = KnowledgeSuggestion::query()->sole();
    expect($suggestion->embedding)->not->toBeNull()
        ->and($suggestion->questions()->sole()->asked_at->toDateTimeString())->toBe('2026-09-15 13:30:00');

    // She teaches the answer and asks to tell whoever went without it.
    livewire('assistant.index')
        ->assertSee('¿Abren los sábados?')
        ->call('teachSuggestion', $suggestion->id)
        ->set('form.answer', 'Sí, los sábados abrimos de 8 a 12.')
        ->call('saveFaq')
        ->assertSee('Avisarle al cliente que preguntó')
        ->call('notifyCustomers');

    $thread = app(Tenant::class)->for($lab->id, fn () => Conversation::query()->sole());
    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::Taught)
        ->and(collect(whatsappSentTo('584141111111'))->last()['text'])->toContain('Sí, los sábados abrimos de 8 a 12.')
        ->and($thread->fresh()->status)->not->toBe(ConversationStatus::Customer)
        ->and($thread->messages()->latest('id')->first()->author)->toBe(MessageAuthor::Assistant);

    // Ana writes back: the ASSISTANT answers, the thread never lands on the team.
    $this->travel(10)->minutes();
    AsistenteAtendia::fake(['Abrimos a las 8.', 'Los sábados abrimos a las 8 en punto.']);
    customerWritesTo('lab-vida', '584141111111', 'Ana', '¡Genial! ¿A qué hora abren exactamente?', 'M3');

    expect(collect(whatsappSentTo('584141111111'))->last()['text'])->toBe('Los sábados abrimos a las 8 en punto.')
        ->and($thread->fresh()->status)->not->toBe(ConversationStatus::Team);

    // The day reads right on the lab's clock, and the win is counted.
    $stats = new Statistics($lab->fresh());
    expect($stats->monthKpis())->toMatchArray(['questions' => 1, 'resolution' => 0, 'recovered' => 1])
        ->and($stats->peakHours['counts'][9])->toBeGreaterThan(0)
        ->and(KnowledgeDocument::query()->where('source_type', 'faq')->count())->toBe(1);

    livewire('assistant.index')->assertSee('1 volvió a escribir');
});
