<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\RememberCustomerFact;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\PlatformContact;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

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

function incomingFor(string $instance, string $phone = '5491122334455', string $pushName = 'Carla'): void
{
    (new ProcessIncomingWhatsAppMessage($instance, $phone, $pushName, 'Hola', 'MSG-'.fake()->unique()->numerify('####')))->handle();
}

test('the first message births the customer and its platform person, all linked', function (): void {
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['Hola.', 'Hola, ¿en qué te ayudo?']);

    incomingFor('atendia-demo');

    $customer = Customer::query()->sole();
    $contact = PlatformContact::query()->sole();

    expect($customer->phone)->toBe('5491122334455')
        ->and($customer->profile_name)->toBe('Carla')
        ->and($customer->first_seen_at)->not->toBeNull()
        ->and($customer->last_activity_at)->not->toBeNull()
        ->and($customer->platform_contact_id)->toBe($contact->id)
        ->and($customer->conversations_count)->toBe(1)
        ->and(Conversation::query()->sole()->customer_id)->toBe($customer->id)
        ->and($contact->businesses_count)->toBe(1)
        ->and($contact->conversations_count)->toBe(1);
});

test('later messages freshen the record and never duplicate it', function (): void {
    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['Hola.', 'Hola.', 'Hola.', 'Hola.']);

    incomingFor('atendia-demo', pushName: 'Carla');
    incomingFor('atendia-demo', pushName: 'Carla ✨');

    expect(Customer::query()->count())->toBe(1)
        ->and(PlatformContact::query()->count())->toBe(1)
        ->and(Customer::query()->sole()->profile_name)->toBe('Carla ✨')
        ->and(PlatformContact::query()->sole()->businesses_count)->toBe(1)
        ->and(PlatformContact::query()->sole()->conversations_count)->toBe(1);
});

test('one person talking to two businesses is two customers but ONE platform contact', function (): void {
    Business::factory()->create(['whatsapp_instance' => 'demo-a']);
    Business::factory()->create(['whatsapp_instance' => 'demo-b']);
    AsistenteAtendia::fake(['Hola.', 'Hola.', 'Hola.', 'Hola.']);

    incomingFor('demo-a');
    incomingFor('demo-b');

    $contact = PlatformContact::query()->sole();

    expect(Customer::query()->count())->toBe(2)
        ->and($contact->businesses_count)->toBe(2)
        ->and($contact->conversations_count)->toBe(2);
});

test('the assistant is briefed with what it knows and carries the remember tool', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'María Pérez']);

    $agent = new AsistenteAtendia($business, null, $customer);

    expect((string) $agent->instructions())
        ->toContain('María Pérez')
        ->toContain('jamás los vuelvas a pedir')
        ->and(collect($agent->tools())->map(fn (object $tool): string => $tool::class)->all())
        ->toContain(RememberCustomerFact::class);
});

test('a learned fact fills an empty column but never overwrites a human value', function (): void {
    $customer = Customer::factory()->create(['name' => null]);

    $customer->rememberFact('name', 'María Pérez');
    expect($customer->refresh()->name)->toBe('María Pérez')
        ->and($customer->ai_extracted['name']['source'])->toBe('ai');

    $customer->forceFill(['name' => 'María P. de Souza'])->save();
    $customer->rememberFact('name', 'Otra Cosa');

    expect($customer->refresh()->name)->toBe('María P. de Souza')
        ->and($customer->ai_extracted['name']['value'])->toBe('Otra Cosa');
});

test('the remember tool rejects unknown fields and malformed emails', function (): void {
    $customer = Customer::factory()->create();
    $tool = new RememberCustomerFact($customer);

    expect((string) $tool->handle(new Request(['field' => 'dni', 'value' => '123'])))->toContain('no guardado')
        ->and((string) $tool->handle(new Request(['field' => 'email', 'value' => 'no-es-correo'])))->toContain('no guardado')
        ->and((string) $tool->handle(new Request(['field' => 'email', 'value' => 'maria@example.com'])))->toBe('Anotado.')
        ->and($customer->refresh()->email)->toBe('maria@example.com');
});

test('the tool files birthdays and seals an explicit opt-in yes', function (): void {
    $customer = Customer::factory()->create();
    $tool = new RememberCustomerFact($customer);

    expect((string) $tool->handle(new Request(['field' => 'birthday', 'value' => '4 de mayo'])))->toContain('no guardado')
        ->and((string) $tool->handle(new Request(['field' => 'birthday', 'value' => '1990-05-04'])))->toBe('Anotado.')
        ->and($customer->refresh()->birthday?->toDateString())->toBe('1990-05-04');

    expect((string) $tool->handle(new Request(['field' => 'marketing_opt_in', 'value' => 'maybe'])))->toContain('no guardado')
        ->and($customer->refresh()->marketing_opt_in_at)->toBeNull()
        ->and((string) $tool->handle(new Request(['field' => 'marketing_opt_in', 'value' => 'yes'])))->toBe('Permiso anotado.')
        ->and($customer->refresh()->marketing_opt_in_at)->not->toBeNull();
});

test('the opt-in briefing appears only while an answer is pending', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
        'marketing_opt_in_requested_at' => now(),
    ]);

    expect((string) (new AsistenteAtendia($business, null, $customer))->instructions())
        ->toContain('marketing_opt_in');

    $customer->sealOptIn();

    expect((string) (new AsistenteAtendia($business, null, $customer->refresh()))->instructions())
        ->not->toContain('marketing_opt_in');
});

test('pre-existing threads are adopted into customers, idempotently', function (): void {
    $a = Business::factory()->create();
    $b = Business::factory()->create();

    // Same person talked to two businesses BEFORE the customer layer existed.
    Conversation::factory()->create(['business_id' => $a->id, 'contact_phone' => '5491122334455', 'contact_name' => 'Carla']);
    Conversation::factory()->create(['business_id' => $b->id, 'contact_phone' => '5491122334455', 'contact_name' => 'Carla']);

    $this->artisan('atendia:adopt-customers')->assertSuccessful();
    $this->artisan('atendia:adopt-customers')->assertSuccessful();

    $contact = PlatformContact::query()->sole();

    expect(Customer::query()->count())->toBe(2)
        ->and(Conversation::query()->whereNull('customer_id')->count())->toBe(0)
        ->and($contact->businesses_count)->toBe(2)
        ->and($contact->conversations_count)->toBe(2)
        ->and(Customer::query()->first()->profile_name)->toBe('Carla');
});

test('birthday greetings reach only today\'s celebrants on connected instances', function (): void {
    $business = Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
    ]);

    Customer::factory()->create([
        'business_id' => $business->id,
        'phone' => '5491111111111',
        'birthday' => now()->subYears(30)->toDateString(),
    ]);
    Customer::factory()->create([
        'business_id' => $business->id,
        'phone' => '5492222222222',
        'birthday' => now()->subYears(25)->addDay()->toDateString(),
    ]);

    $this->artisan('atendia:birthday-greetings')->assertSuccessful();

    Http::assertSentCount(1);
});

test('an impossible birthday is refused instead of rolled over to another date', function (): void {
    $customer = Customer::factory()->create();
    $tool = new RememberCustomerFact($customer);

    expect((string) $tool->handle(new Request(['field' => 'birthday', 'value' => '1990-13-45'])))->toContain('no guardado')
        ->and($customer->refresh()->birthday)->toBeNull();
});
