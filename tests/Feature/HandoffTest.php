<?php

declare(strict_types=1);

use App\Actions\Business\SendHumanReply;
use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\ReplyTranslator;
use App\Ai\Tools\EscalateToHuman;
use App\Enums\ConversationStatus;
use App\Enums\HandoffLevel;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');

    Cache::flush();

    $this->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embedOne')
        ->andReturn(array_fill(0, 1536, 0.001))
        ->byDefault();

    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);
});

/** @return list<ClientRequest> */
function handoffSentTexts(): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): ClientRequest => $pair[0], Http::recorded()->all()),
        fn (ClientRequest $request): bool => str_contains($request->url(), '/message/sendText/'),
    ));
}

test('escalating flips the thread to team and pings the owner in Spanish', function (): void {
    $business = Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'fallback_whatsapp_number' => '+54 9 299 555-0000',
    ]);
    $thread = Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    $reply = (string) (new EscalateToHuman($business, $thread))
        ->handle(new Request(['reason' => 'Pide una cotización grande.']));

    expect($thread->refresh()->status)->toBe(ConversationStatus::Team)
        ->and($reply)->toContain('despedite');

    $alert = handoffSentTexts()[0];
    expect($alert['number'])->toBe('5492995550000')
        ->and($alert['text'])->toContain('Carla')
        ->and($alert['text'])->toContain('Pide una cotización grande.');
});

test('with no fallback number the escalation still lands, just unpinged', function (): void {
    $business = Business::factory()->create(['fallback_whatsapp_number' => null]);
    $thread = Conversation::factory()->create(['business_id' => $business->id]);

    (new EscalateToHuman($business, $thread))->handle(new Request(['reason' => 'Queja.']));

    expect($thread->refresh()->status)->toBe(ConversationStatus::Team)
        ->and(handoffSentTexts())->toBe([]);
});

test('a team thread keeps the record but the assistant stays quiet in it', function (): void {
    $business = Business::factory()->create(['whatsapp_instance' => 'demo']);
    Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491122334455',
        'status' => ConversationStatus::Team,
    ]);

    AsistenteAtendia::fake(['Nunca debería salir.', 'Nunca debería salir.']);

    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', 'Sigo esperando', 'MSG-77'))->handle();

    $thread = Conversation::query()->sole();

    expect(handoffSentTexts())->toBe([])
        ->and($thread->messages()->count())->toBe(1)
        ->and($thread->messages()->sole()->body)->toBe('Sigo esperando');
});

test('the briefing carries the three families and obeys the dial', function (): void {
    $thread = Conversation::factory()->create();

    $minimal = Business::factory()->create(['handoff_level' => HandoffLevel::Minimal]);
    $eager = Business::factory()->create(['handoff_level' => HandoffLevel::Eager]);

    $minimalText = (string) (new AsistenteAtendia($minimal, $thread))->instructions();
    $eagerText = (string) (new AsistenteAtendia($eager, $thread))->instructions();

    expect($minimalText)->toContain('MAYÚSCULAS')
        ->toContain('no quiero hablar con un bot')
        ->toContain('SOLO si el cliente lo pide')
        ->and($eagerText)->toContain('ENSEGUIDA')
        ->and(collect((new AsistenteAtendia($eager, $thread))->tools())->map(fn (object $tool): string => $tool::class)->all())
        ->toContain(EscalateToHuman::class);
});

test('the owner writes their own cases and the briefing makes them absolute', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create(['handoff_level' => HandoffLevel::Minimal]))->save();
    $this->actingAs($user);

    livewire('assistant.settings')
        ->assertSee(__('client.assistant.handoff_rules_label'))
        ->set('handoffForm.rules', "Si describe síntomas\nSi pide un diagnóstico")
        ->call('saveHandoffRules');

    $business = $user->business->refresh();
    expect($business->handoff_rules)->toBe("Si describe síntomas\nSi pide un diagnóstico");

    $thread = Conversation::factory()->create(['business_id' => $business->id]);
    $instructions = (string) (new AsistenteAtendia($business, $thread))->instructions();

    // The owner's cases outrank even the "almost never" dial.
    expect($instructions)->toContain('REGLAS PROPIAS')
        ->toContain('Si describe síntomas')
        ->toContain('SIEMPRE derivan');
});

test('with no owner cases the briefing skips that section', function (): void {
    $business = Business::factory()->create(['handoff_rules' => null]);
    $thread = Conversation::factory()->create(['business_id' => $business->id]);

    expect((string) (new AsistenteAtendia($business, $thread))->instructions())
        ->not->toContain('REGLAS PROPIAS');
});

test('the owner tunes the dial from the assistant screen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $this->actingAs($user);

    $this->get(route('assistant.settings'))
        ->assertSuccessful()
        ->assertSee(__('client.assistant.settings_title'));

    livewire('assistant.settings')
        ->assertSee(__('client.assistant.handoff_title'))
        ->set('handoffLevel', 'minimal');

    expect($user->business->refresh()->handoff_level)->toBe(HandoffLevel::Minimal);

    livewire('assistant.settings')
        ->set('handoffLevel', 'banana')
        ->assertSet('handoffLevel', 'minimal');

    expect($user->business->refresh()->handoff_level)->toBe(HandoffLevel::Minimal);
});

test('the composer answers from the panel and passes the ball to the customer', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
    ]))->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_phone' => '5491111111111',
        'status' => ConversationStatus::Team,
    ]);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.reply_placeholder'))
        ->set('reply', 'Te preparo la cotización y te escribo en una hora.')
        ->call('sendReply')
        ->assertSet('reply', '');

    $thread->refresh();
    $sent = handoffSentTexts()[0];

    expect($thread->status)->toBe(ConversationStatus::Customer)
        ->and($thread->messages()->sole()->author)->toBe(MessageAuthor::Human)
        ->and($sent['number'])->toBe('5491111111111')
        ->and($sent['text'])->toBe('Te preparo la cotización y te escribo en una hora.');
});

test('the owner can take an open thread by hand, and the composer stays away from open ones', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $thread = Conversation::factory()->create(['business_id' => $user->business_id]);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.takeover'))
        ->assertDontSee(__('client.conversations.reply_placeholder'))
        ->call('takeover');

    expect($thread->refresh()->status)->toBe(ConversationStatus::Team);
});

test('the customer answering a human flips the ball back to the team, silently', function (): void {
    $business = Business::factory()->create(['whatsapp_instance' => 'demo']);
    Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491122334455',
        'status' => ConversationStatus::Customer,
    ]);

    AsistenteAtendia::fake(['Nunca.', 'Nunca.']);

    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', 'Dale, la espero', 'MSG-88'))->handle();

    expect(handoffSentTexts())->toBe([])
        ->and(Conversation::query()->sole()->status)->toBe(ConversationStatus::Team);
});

test('a resolved thread reopens for the assistant on the customer\'s next word', function (): void {
    $business = Business::factory()->create(['whatsapp_instance' => 'demo']);
    Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491122334455',
        'status' => ConversationStatus::Resolved,
    ]);

    AsistenteAtendia::fake(['Hola.', 'Hola, ¿en qué te ayudo?']);

    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', 'Otra consulta', 'MSG-89'))->handle();

    expect(Conversation::query()->sole()->status)->toBe(ConversationStatus::Open)
        ->and(handoffSentTexts())->toHaveCount(1);
});

test('marking resolved closes the loop from the panel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'status' => ConversationStatus::Customer,
    ]);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.status_customer'))
        ->call('markResolved');

    expect($thread->refresh()->status)->toBe(ConversationStatus::Resolved);
});

test('an internal note stays home: no WhatsApp, no assistant memory, no preview', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'status' => ConversationStatus::Team,
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->set('reply', 'Le prometí 10% si confirma hoy.')
        ->call('saveNote')
        ->assertSee('Le prometí 10% si confirma hoy.')
        ->assertSet('reply', '');

    $note = $thread->messages()->where('kind', MessageKind::Note)->sole();

    expect(handoffSentTexts())->toBe([])
        ->and($thread->refresh()->latestMessage->body)->toBe('Hola')
        ->and(collect((new AsistenteAtendia($user->business, $thread))->messages())
            ->contains(fn ($message): bool => str_contains($message->content ?? '', '10%')))->toBeFalse()
        ->and($note->author)->toBe(MessageAuthor::Human);
});

test('a human reply travels in the customer\'s language, recorded as sent', function (): void {
    $business = Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));
    $thread = Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491111111111',
        'language' => 'en',
        'status' => ConversationStatus::Team,
    ]);

    ReplyTranslator::fake([['text' => 'We ship tomorrow morning.']]);

    app(SendHumanReply::class)->handle($business, $thread, 'Enviamos mañana a la mañana.');

    expect(handoffSentTexts()[0]['text'])->toBe('We ship tomorrow morning.')
        ->and($thread->messages()->sole()->body)->toBe('We ship tomorrow morning.');
});

test('a Spanish-speaking thread skips the translator entirely', function (): void {
    $business = Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
    ]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));
    $thread = Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491111111111',
        'language' => 'es',
        'status' => ConversationStatus::Team,
    ]);

    app(SendHumanReply::class)->handle($business, $thread, 'Enviamos mañana.');

    expect(handoffSentTexts()[0]['text'])->toBe('Enviamos mañana.');
});

test('a forgotten thread reminds owner and customer once, and only past the grace window', function (): void {
    config()->set('atendia.handoff.reminder_minutes', 20);

    $business = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '5492995550000',
    ]);

    Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
        'status' => ConversationStatus::Team,
        'escalated_at' => now()->subMinutes(30),
    ]);
    Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5492222222222',
        'status' => ConversationStatus::Team,
        'escalated_at' => now()->subMinutes(5),
    ]);

    $this->artisan('atendia:handoff-reminders')->assertSuccessful();

    $texts = handoffSentTexts();

    // Only the 30-minute thread fires: customer hold + owner ping.
    expect($texts)->toHaveCount(2)
        ->and($texts[0]['number'])->toBe('5491111111111')
        ->and($texts[0]['text'])->toContain('Laboratorio Vida')
        ->and($texts[1]['number'])->toBe('5492995550000')
        ->and($texts[1]['text'])->toContain('Carla');

    // The second sweep stays silent: one reminder per escalation.
    $this->artisan('atendia:handoff-reminders')->assertSuccessful();
    expect(handoffSentTexts())->toHaveCount(2);
});

test('the escalation clock restarts when the customer sends the ball back', function (): void {
    $business = Business::factory()->create(['whatsapp_instance' => 'demo']);
    $thread = Conversation::factory()->create([
        'business_id' => $business->id,
        'contact_phone' => '5491122334455',
        'status' => ConversationStatus::Customer,
        'handoff_reminded_at' => now()->subHour(),
    ]);

    AsistenteAtendia::fake(['Nunca.', 'Nunca.']);

    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', 'Dale', 'MSG-90'))->handle();

    $thread->refresh();

    expect($thread->status)->toBe(ConversationStatus::Team)
        ->and($thread->escalated_at)->not->toBeNull()
        ->and($thread->handoff_reminded_at)->toBeNull();
});

test('the owner hands the thread back with one click', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $customer = Customer::factory()->create(['business_id' => $user->business_id, 'phone' => '5491111111111']);
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_phone' => '5491111111111',
        'customer_id' => $customer->id,
        'status' => ConversationStatus::Team,
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->assertSee(__('client.conversations.status_team_chip'))
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.status_team'))
        ->assertSee(__('client.conversations.resume'))
        ->call('resume');

    expect($thread->refresh()->status)->toBe(ConversationStatus::Open);
});
