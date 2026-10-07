<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\SearchCatalog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\AssistantSkillSeeder;
use Database\Seeders\BusinessActivitySeeder;
use Database\Seeders\BusinessSectorSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceModalitySeeder;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Mockery\MockInterface;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The journey — the only test that measures the product, not a screen
|--------------------------------------------------------------------------
| Every other browser test hands its screen a factory-built world and then
| checks that the screen draws it. None of them pulls the chain: a wizard
| that stopped sealing the tenant, or a register that stopped assigning the
| role, leaves all of them green.
|
| This one builds NOTHING the interface is supposed to build. A person with
| an empty database registers, creates her business, loads what she sells,
| prices it, connects her number, and a customer's question walks in. Each
| step's input is the previous step's output, so a broken link cannot hide.
|
| Only the catalog the platform curates (countries, sectors, trades, plans)
| is seeded: that is the admin's data, not hers.
*/

beforeEach(function (): void {
    app()->setLocale('es');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(PlanSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(ProvinceSeeder::class);
    $this->seed(BusinessSectorSeeder::class);
    $this->seed(BusinessActivitySeeder::class);
    $this->seed(ServiceModalitySeeder::class);
    $this->seed(ServiceTypeSeeder::class);
    $this->seed(AssistantSkillSeeder::class);

    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.webhook_url', 'http://atendia-app/api/webhooks/evolution');
    config()->set('services.evolution.webhook_secret', 'test-secret');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');

    // The suite never rides the network: one fixed vector, enough for the
    // catalog match to find the only row. Both doors are stubbed — indexing
    // calls embed(), searches call embedOne() — or a real save dies mid-request.
    $this->mock(KnowledgeEmbedder::class, function (MockInterface $mock): void {
        $mock->shouldReceive('embedOne')->andReturn(array_fill(0, 1536, 0.001))->byDefault();
        $mock->shouldReceive('embed')->andReturnUsing(
            fn (array $texts): array => array_map(fn (): array => array_fill(0, 1536, 0.001), array_values($texts)),
        )->byDefault();
    });

    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING', 'base64' => 'data:image/png;base64,QQ==']),
    ]);
});

/**
 * The advance button of the step currently on screen: three more sit hidden.
 * Scoped to the footer since step 5 also offers a primary "connect" button.
 */
const JOURNEY_STEP_CTA = '.wizard-panel > div:not([hidden]) .wizard-foot button.btn-primary';

/** What Evolution was asked to send out, in order. @return list<HttpRequest> */
function journeySentTexts(): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): HttpRequest => $pair[0], Http::recorded()->all()),
        fn (HttpRequest $request): bool => str_contains($request->url(), '/message/sendText/'),
    ));
}

test('a person registers, loads her offer and her own price reaches the assistant', function (): void {
    // ---- 1. The account. Nothing exists yet: she types it herself. ----
    $page = visit('/register');

    $page->assertNoJavaScriptErrors()
        ->fill('name', 'Mariana Ortiz')
        ->fill('email', 'mariana@laboratoriovida.test')
        ->fill('password', 'Password!123')
        ->fill('password_confirmation', 'Password!123')
        ->click('text=Crear mi cuenta >> visible=true');

    // Registering lands in the wizard, which is the next step of the journey
    // and not a screen she had to go looking for.
    $page->assertSee(__('wizard.steps.2.heading'));

    // ---- 2. The business. This is what seals the tenant. ----
    $page->fill('#if-name', 'Laboratorio Vida')
        ->assertSee('Soy el asistente de')
        ->assertSee('Laboratorio Vida');

    // Each pick is a `.live` round-trip that re-renders the field below it.
    $page->fill('#if-country_id', 'argentina')->click('text=Argentina >> visible=true')->wait(1);
    $page->fill('#if-province_id', 'santa fe')->click('text=Santa Fe >> visible=true')->wait(1);

    $page->click('text=Salud >> visible=true')->wait(1);
    $page->click('text=Laboratorio de análisis >> visible=true')->wait(1);

    $page->click(JOURNEY_STEP_CTA)->assertNoJavaScriptErrors()->wait(1);

    // ---- 3. What she sells, in her own words (not a suggestion chip). ----
    $page->assertSee(__('wizard.steps.3.heading'))
        ->fill('#if-service_draft', 'Ecodoppler')
        ->keys('#if-service_draft', 'Enter')
        ->wait(1);

    $page->click(JOURNEY_STEP_CTA)->assertNoJavaScriptErrors()->wait(1);

    // ---- 4. A product, typed one by one (the sheet import has its own test). ----
    $page->assertSee(__('wizard.steps.4.heading'))
        ->fill('#if-product_draft', 'Alcohol en gel 250 ml')
        ->keys('#if-product_draft', 'Enter')
        ->wait(1);

    $page->click(JOURNEY_STEP_CTA)->assertNoJavaScriptErrors()->wait(1);

    // ---- 5. The numbers her customers write to. ----
    $page->assertSee(__('wizard.steps.5.heading'))
        ->fill('#if-whatsapp_number', '3415124408')
        ->fill('#if-fallback_whatsapp_number', '3415550199')
        ->fill('#if-email', 'hola@laboratoriovida.test')
        ->click(JOURNEY_STEP_CTA)
        ->wait(1);

    // The closing recap reads the database, so it is the first proof that
    // every step above actually persisted.
    $page->assertSee(__('wizard.done.heading'))
        ->assertSee(__('wizard.done.recap.name', ['name' => 'Laboratorio Vida']))
        ->screenshot(filename: 'journey-wizard-done');

    $business = Business::query()->sole();
    $owner = User::query()->sole();

    expect($owner->business_id)->toBe($business->id)
        ->and($owner->hasRole('client'))->toBeTrue()
        ->and($business->serviceNames())->toBe(['Ecodoppler'])
        ->and($business->productNames())->toBe(['Alcohol en gel 250 ml']);

    // ---- 6. The price. The wizard takes names; the panel puts a number on them. ----
    $page = visit('/servicios');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Ecodoppler')
        ->click('[aria-label="Editar Ecodoppler"]')
        ->fill('#if-price', '8000')
        ->click('text='.__('client.services.sheet_save').' >> visible=true')
        // The save closes the sheet and repaints the row; under load that
        // round-trip outlasts the default retry and the price "vanishes".
        ->waitForText('8.000', 15);

    $page->screenshot(filename: 'journey-service-priced');

    expect(Service::query()->sole()->price)->not->toBeNull();

    // ---- 7. The number, connected. The QR scan is the one human step that
    // cannot be automated, so the journey drives the same action the screen
    // calls and then the real `connection.update` delivery Evolution sends.
    $this->actingAs($owner);

    livewire('whatsapp.link')->call('connect');

    expect($business->refresh()->whatsapp_instance)->toBe('business-'.$business->id);

    $this->postJson('/api/webhooks/evolution', [
        'event' => 'connection.update',
        'instance' => $business->whatsapp_instance,
        'data' => ['state' => 'open'],
    ], ['X-Webhook-Secret' => 'test-secret'])->assertOk();

    expect($business->refresh()->isConnected())->toBeTrue();

    // ---- 8. A customer asks. Real webhook, real job, real thread. ----
    // Two canned turns: a fake carries no tool calls, so the agent always
    // re-asks once and the customer gets the second, grounded pass.
    AsistenteAtendia::fake(['Dejame ver.', 'El Ecodoppler sale $ 8.000.']);

    $this->postJson('/api/webhooks/evolution', [
        'event' => 'messages.upsert',
        'instance' => $business->whatsapp_instance,
        'data' => [
            'key' => ['remoteJid' => '5493415557788@s.whatsapp.net', 'fromMe' => false, 'id' => 'MSG-JOURNEY-1'],
            'pushName' => 'Carla Ruiz',
            'message' => ['conversation' => '¿Cuánto sale el ecodoppler?'],
        ],
    ], ['X-Webhook-Secret' => 'test-secret'])->assertOk();

    // The question opened a thread under HER business, with the customer the
    // assistant captured, and an answer left through HER instance.
    $conversation = Conversation::query()->sole();

    expect($conversation->business_id)->toBe($business->id)
        ->and(Customer::withoutGlobalScopes()->sole()->business_id)->toBe($business->id)
        ->and(journeySentTexts())->toHaveCount(1)
        ->and(journeySentTexts()[0]['number'])->toBe('5493415557788');

    // ---- 9. The link that matters: the skill the model calls to answer a
    // price hands back the number SHE typed on screen in step 6. A faked
    // model cannot prove its own wording, but this proves the data it reads.
    $answer = (string) (new SearchCatalog($business))->handle(new Request(['item' => 'ecodoppler']));

    // Spelled exactly as the screen spells it. They used to disagree —
    // "$ 8.000" for her, "8000" for her customer — and two spellings of one
    // price read as two prices.
    expect($answer)->toContain('Servicio: Ecodoppler')->toContain('$ 8.000');
});
