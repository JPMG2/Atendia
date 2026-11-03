<?php

declare(strict_types=1);

use App\Ai\Agents\BusinessBioWriter;
use App\Ai\Agents\CatalogCopywriter;
use App\Models\AiUsage;
use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| What the app needs before Google Play (tanda B), on the server side
|--------------------------------------------------------------------------
| "Optimizar con IA" in the three places the web has it, under the web's
| shared daily ceiling; the phone field's country list; and the variant of
| Spanish the server speaks, so the app's own words follow it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Queue::fake();
});

function asTandaApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** Today's uses of the ceiling, as the meter would have written them. */
function spendTandaUses(User $owner, int $times): void
{
    for ($i = 0; $i < $times; $i++) {
        AiUsage::query()->create(['business_id' => $owner->business_id, 'kind' => 'BusinessBioWriter', 'model' => 'gpt-6-luna', 'input_tokens' => 0, 'cached_tokens' => 0, 'output_tokens' => 0]);
    }
}

describe('Optimizar con IA, identity', function (): void {
    it('proposes a description and never saves it, with the counter of the day', function (): void {
        $owner = payingUser([], ['name' => 'Clínica Vida', 'description' => 'La de antes']);
        BusinessBioWriter::fake([['description' => 'Clínica Vida atiende odontología general en Valencia.']]);

        $answer = asTandaApp($owner)->postJson('/api/v1/business/identity/description', ['name' => 'Clínica Vida'])->assertOk();

        expect($answer->json('data.description'))->toBe('Clínica Vida atiende odontología general en Valencia.')
            ->and($answer->json('message'))->toBe(__('client.business.identity.ai_done'))
            ->and($answer->json('data.ai.note'))->toBeString()
            ->and($owner->business->fresh()->description)->toBe('La de antes');
    });

    it('answers the web sentence and never calls the model once the day is spent', function (): void {
        $owner = payingUser();
        spendTandaUses($owner, 10);
        BusinessBioWriter::fake()->preventStrayPrompts();

        asTandaApp($owner)->postJson('/api/v1/business/identity/description')
            ->assertStatus(429)
            ->assertJsonPath('code', 'ai_limit')
            ->assertJsonPath('ai.reached', true);
    });

    it('says the model did not answer instead of pretending', function (): void {
        $owner = payingUser();
        BusinessBioWriter::fake(fn () => throw new RuntimeException('down'));

        asTandaApp($owner)->postJson('/api/v1/business/identity/description')
            ->assertStatus(502)
            ->assertJsonPath('message', __('client.business.identity.ai_failed'));
    });

    it('carries the button words and the counter in the identity card', function (): void {
        $owner = payingUser();

        $card = asTandaApp($owner)->getJson('/api/v1/business/identity')->assertOk();

        expect($card->json('data.ai.action'))->toBe(__('client.business.identity.ai_action'))
            ->and($card->json('data.ai.field'))->toBe('description')
            ->and($card->json('data.ai.reached'))->toBeFalse();
    });
});

describe('Optimizar con IA, services and products', function (): void {
    it('writes and saves the descriptions a menu is missing, in the web words', function (): void {
        $owner = payingUser();
        $service = Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Limpieza dental', 'description' => null]);
        CatalogCopywriter::fake([['items' => [['name' => 'Limpieza dental', 'description' => 'Limpieza profunda con ultrasonido.']]]]);

        asTandaApp($owner)->postJson('/api/v1/services/descriptions')
            ->assertOk()
            ->assertJsonPath('data.written', 1)
            ->assertJsonPath('message', trans_choice('client.services.ai_done', 1, ['count' => 1]));

        expect($service->fresh()->description)->toBe('Limpieza profunda con ultrasonido.');
    });

    it('does the same for products, and the lists carry the banner and its counter', function (): void {
        $owner = payingUser();
        $product = Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Cepillo', 'description' => null]);
        CatalogCopywriter::fake([['items' => [['name' => 'Cepillo', 'description' => 'Cerdas suaves.']]]]);

        asTandaApp($owner)->postJson('/api/v1/products/descriptions')->assertOk()->assertJsonPath('data.written', 1);
        expect($product->fresh()->description)->toBe('Cerdas suaves.');

        $list = asTandaApp($owner)->getJson('/api/v1/products')->assertOk();
        expect($list->json('data.copy.ai.action'))->toBe(__('client.products.ai_action'))
            ->and($list->json('data.ai.note'))->toBeString();
        expect(asTandaApp($owner)->getJson('/api/v1/services')->json('data.copy.ai.title'))->toBe(__('client.services.ai_title'));
    });

    it('shares the one daily ceiling of the web', function (): void {
        $owner = payingUser();
        Service::factory()->create(['business_id' => $owner->business_id, 'description' => null]);
        spendTandaUses($owner, 10);
        CatalogCopywriter::fake()->preventStrayPrompts();

        asTandaApp($owner)->postJson('/api/v1/services/descriptions')->assertStatus(429)->assertJsonPath('code', 'ai_limit');
    });

    it('is the owner\'s: an agent of the team is turned away from all three', function (): void {
        $owner = payingUser();
        $agent = User::factory()->create(['business_id' => $owner->business_id]);
        $agent->syncRoles(['agent']);

        asTandaApp($agent)->postJson('/api/v1/business/identity/description')->assertForbidden();
        asTandaApp($agent)->postJson('/api/v1/services/descriptions')->assertForbidden();
        asTandaApp($agent)->postJson('/api/v1/products/descriptions')->assertForbidden();
    });
});

it('gives a phone field the web country list, the business country first chosen', function (): void {
    // The catalog stores the dial code bare ("58"), as the real table holds every active country.
    $venezuela = Country::factory()->create(['name' => 'Venezuela', 'iso2' => 'VE', 'phone_code' => '58', 'is_active' => true]);
    Country::factory()->create(['name' => 'Inactivo', 'iso2' => 'XX', 'phone_code' => '999', 'is_active' => false]);
    $owner = payingUser([], ['country_id' => $venezuela->id]);

    $answer = asTandaApp($owner)->getJson('/api/v1/phone-countries')->assertOk();

    expect($answer->json('data.default'))->toBe('58')
        ->and(collect($answer->json('data.countries'))->firstWhere('name', 'Venezuela'))->toBe(['name' => 'Venezuela', 'code' => '58', 'flag' => '🇻🇪'])
        ->and(collect($answer->json('data.countries'))->pluck('name'))->not->toContain('Inactivo')
        ->and($answer->json('data.search'))->toBe(__('forms.phone.search'));
});

it('tells the app which variant of Spanish it resolved', function (string $header, string $locale): void {
    test()->withHeaders(['Accept-Language' => $header])->getJson('/api/v1/brand')->assertOk()->assertJsonPath('data.locale', $locale);
})->with([
    'Argentina' => ['es-AR', 'es_AR'],
    'Venezuela' => ['es-VE', 'es_VE'],
    'neutral' => ['es', 'es'],
]);
