<?php

declare(strict_types=1);

use App\Classes\Main\Client;
use App\Enums\ModerationSeverity;
use App\Events\BusinessConnectionSaved;
use App\Livewire\Forms\Client\BillingForm;
use App\Livewire\Forms\Client\ContactForm;
use App\Livewire\Forms\Client\IdentityForm;
use App\Livewire\Forms\Client\LocationForm;
use App\Models\Business;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ModerationFlag;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use App\Services\ContentModeration;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| The editable cards of "Mi negocio", over the API
|--------------------------------------------------------------------------
| The phone saves through the web's own rules and Actions: what a card
| accepts here is what the web card accepts, and the business is always the
| caller's.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    // Saving a card re-indexes the profile for the assistant, which asks OpenAI: not what these tests prove.
    Queue::fake();
});

function asCardsApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function fieldOf(array $card, string $key): array
{
    return collect($card['fields'])->firstWhere('key', $key);
}

describe('reading a card', function (): void {
    it('describes each card with the web\'s own words and what the business holds today', function (): void {
        $owner = payingUser(business: ['name' => 'Panadería Sol', 'description' => 'Pan de masa madre.', 'address' => 'Calle 5', 'city' => 'Mérida', 'has_premises' => true, 'email' => 'hola@sol.test']);

        $identity = asCardsApp($owner)->getJson('/api/v1/business/identity')->assertOk()->json('data');

        expect($identity['copy']['title'])->toBe('Identidad')
            ->and(fieldOf($identity, 'name')['value'])->toBe('Panadería Sol')
            ->and(fieldOf($identity, 'name')['required'])->toBeTrue()
            ->and(fieldOf($identity, 'description')['max'])->toBe(500)
            ->and(fieldOf($identity, 'description')['hint'])->toBe(__('client.business.identity.description_hint'));

        $location = asCardsApp($owner)->getJson('/api/v1/business/location')->assertOk()->json('data');

        expect(fieldOf($location, 'has_premises')['value'])->toBeTrue()
            ->and(fieldOf($location, 'address')['value'])->toBe('Calle 5')
            ->and(fieldOf($location, 'address')['show_when'])->toBe(['key' => 'has_premises', 'value' => true]);

        $contact = asCardsApp($owner)->getJson('/api/v1/business/contact')->assertOk()->json('data');

        expect(fieldOf($contact, 'email')['value'])->toBe('hola@sol.test');
    });

    it('splits a stored phone into its dial and number, as the web\'s two boxes do', function (): void {
        $owner = payingUser(business: ['whatsapp_number' => '+58 4247673951', 'fallback_whatsapp_number' => '3415550199']);

        $contact = asCardsApp($owner)->getJson('/api/v1/business/contact')->assertOk()->json('data');

        expect(fieldOf($contact, 'whatsapp_number')['value'])->toBe(['dial' => '58', 'number' => '4247673951'])
            ->and(fieldOf($contact, 'fallback_whatsapp_number')['value']['number'])->toBe('3415550199');
    });

    it('says which piece of the agenda is still missing, and hands over the booking link, only with the agenda on', function (): void {
        $owner = payingUser(business: ['appointments_enabled' => false]);

        expect(asCardsApp($owner)->getJson('/api/v1/business/appointments')->assertOk()->json('data.share'))->toBeNull();

        $owner->business->forceFill(['appointments_enabled' => true])->save();
        $card = asCardsApp($owner)->getJson('/api/v1/business/appointments')->assertOk()->json('data');

        expect($card['note'])->toBe(__('client.business.appointments.needs_hours'))
            ->and($card['share']['url'])->toBe($owner->business->bookingLink())
            ->and($card['share']['url'])->not->toBe('');
    });

    it('offers only the active currencies and the tax conditions of the business\'s own country', function (): void {
        $owner = payingUser();
        $own = TaxCondition::factory()->create(['country_id' => $owner->business->country_id, 'is_active' => true]);
        $foreign = TaxCondition::factory()->create(['country_id' => Country::factory()->create()->id, 'is_active' => true]);
        $off = Currency::factory()->create(['is_active' => false]);

        $card = asCardsApp($owner)->getJson('/api/v1/business/billing')->assertOk()->json('data');
        $conditions = collect(fieldOf($card, 'tax_condition_id')['options'])->pluck('value');
        $currencies = collect(fieldOf($card, 'currency_id')['options'])->pluck('value');

        expect($conditions)->toContain($own->id)->not->toContain($foreign->id)
            ->and($currencies)->not->toContain($off->id);
    });

    it('is the owner\'s alone: an agent is refused and an unknown card does not exist', function (): void {
        $owner = payingUser();
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        asCardsApp($agent->refresh())->getJson('/api/v1/business/identity')->assertForbidden();
        asCardsApp($agent)->putJson('/api/v1/business/identity', ['name' => 'Hackeado'])->assertForbidden();
        asCardsApp($owner)->getJson('/api/v1/business/team')->assertNotFound();
    });
});

describe('saving a card', function (): void {
    it('saves the identity through the web\'s Action and answers with what was stored', function (): void {
        $owner = payingUser(business: ['name' => 'Viejo']);

        $response = asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => 'Panadería Sol', 'description' => 'Pan de masa madre.'])->assertOk();

        expect($owner->business->fresh())->name->toBe('Panadería Sol')->description->toBe('Pan de masa madre.')
            ->and($response->json('message'))->not->toBe('')
            ->and(fieldOf($response->json('data'), 'name')['value'])->toBe('Panadería Sol');
    });

    it('refuses what the web\'s identity card refuses', function (): void {
        $owner = payingUser(business: ['name' => 'Viejo']);

        asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => 'ab'])->assertUnprocessable()->assertJsonValidationErrors('name');
        asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => '<script>alert(1)</script>'])->assertUnprocessable()->assertJsonValidationErrors('name');
        asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => 'Bien', 'description' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('description');

        expect($owner->business->fresh()->name)->toBe('Viejo');
    });

    it('uses the very rules of the web forms, not a copy', function (): void {
        $owner = payingUser();
        $cards = Client::for($owner)->businessCards;

        expect($cards->rulesFor('identity'))->toEqual(IdentityForm::fieldRules())
            ->and($cards->rulesFor('location'))->toEqual(LocationForm::fieldRules())
            ->and($cards->rulesFor('contact'))->toEqual(ContactForm::fieldRules())
            ->and($cards->rulesFor('billing'))->toEqual(BillingForm::fieldRules($owner->business->country_id));
    });

    it('never takes the business from the body: a business_id and a foreign name change nothing of anyone else', function (): void {
        $owner = payingUser(business: ['name' => 'Mío']);
        $other = Business::factory()->create(['name' => 'Ajeno']);

        asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => 'Nuevo nombre', 'business_id' => $other->id, 'id' => $other->id])->assertOk();

        expect($other->fresh()->name)->toBe('Ajeno')
            ->and($owner->business->fresh()->name)->toBe('Nuevo nombre');
    });

    it('saves the location, answers "no premises" and keeps the answer', function (): void {
        $owner = payingUser(business: ['has_premises' => null]);

        asCardsApp($owner)->putJson('/api/v1/business/location', ['has_premises' => true, 'address' => '  Av.   Bolívar  Norte, local 4 ', 'city' => 'Valencia'])->assertOk();

        expect($owner->business->fresh())->has_premises->toBeTrue()->address->toBe('Av. Bolívar Norte, local 4')->city->toBe('Valencia');

        asCardsApp($owner)->putJson('/api/v1/business/location', ['has_premises' => false, 'address' => null, 'city' => null])->assertOk();

        expect($owner->business->fresh())->has_premises->toBeFalse()->address->toBeNull();
    });

    it('saves the bookings and refuses a slot the web refuses', function (): void {
        $owner = payingUser();

        asCardsApp($owner)->putJson('/api/v1/business/appointments', ['appointments_enabled' => true, 'appointment_capacity' => 3, 'appointment_slot_minutes' => 45, 'appointments_per_day' => 20])->assertOk();

        expect($owner->business->fresh())->appointments_enabled->toBeTrue()->appointment_capacity->toBe(3)->appointment_slot_minutes->toBe(45)->appointments_per_day->toBe(20);

        asCardsApp($owner)->putJson('/api/v1/business/appointments', ['appointments_enabled' => true, 'appointment_capacity' => 0, 'appointment_slot_minutes' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors(['appointment_capacity', 'appointment_slot_minutes']);
    });

    it('saves the contact, tells the welcome mail whether it was the first address, and refuses a bad one', function (): void {
        Event::fake([BusinessConnectionSaved::class]);
        $owner = payingUser(business: ['email' => null]);

        asCardsApp($owner)->putJson('/api/v1/business/contact', ['whatsapp_number' => '+58 4247673951', 'email' => 'hola@sol.test', 'web' => 'https://sol.test'])->assertOk();

        Event::assertDispatched(BusinessConnectionSaved::class, fn (BusinessConnectionSaved $event): bool => $event->hadEmail === false);
        expect($owner->business->fresh())->email->toBe('hola@sol.test')->whatsapp_number->toBe('+58 4247673951');

        asCardsApp($owner)->putJson('/api/v1/business/contact', ['email' => 'no-es-un-correo', 'web' => 'ftp://x'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'web']);
    });

    it('saves the currency card, lower-cases the billing mail and refuses a tax condition of another country', function (): void {
        $owner = payingUser();
        $currency = Currency::factory()->create(['is_active' => true]);
        $own = TaxCondition::factory()->create(['country_id' => $owner->business->country_id, 'is_active' => true]);
        $foreign = TaxCondition::factory()->create(['country_id' => Country::factory()->create()->id, 'is_active' => true]);

        asCardsApp($owner)->putJson('/api/v1/business/billing', ['currency_id' => $currency->id, 'tax_condition_id' => $own->id, 'tax_id' => ' J-123  ', 'billing_email' => 'COBROS@Sol.TEST'])->assertOk();

        expect($owner->business->fresh())->currency_id->toBe($currency->id)->tax_id->toBe('J-123')->billing_email->toBe('cobros@sol.test');

        asCardsApp($owner)->putJson('/api/v1/business/billing', ['tax_condition_id' => $foreign->id, 'billing_email' => 'ok@sol.test'])->assertUnprocessable()->assertJsonValidationErrors('tax_condition_id');
        asCardsApp($owner)->putJson('/api/v1/business/billing', ['currency_id' => $currency->id, 'reference_currency_id' => $currency->id, 'billing_email' => 'ok@sol.test'])->assertUnprocessable()->assertJsonValidationErrors('reference_currency_id');
        asCardsApp($owner)->putJson('/api/v1/business/billing', ['billing_email' => ''])->assertUnprocessable()->assertJsonValidationErrors('billing_email');
    });
});

describe('a partial save', function (): void {
    it('keeps every field the body does not mention, whatever the card', function (): void {
        $owner = payingUser(business: [
            'name' => 'Sol', 'description' => 'Pan de masa madre.', 'has_premises' => true, 'address' => 'Calle 5', 'city' => 'Mérida',
            'appointments_enabled' => true, 'appointment_capacity' => 2, 'appointment_slot_minutes' => 30, 'appointments_per_day' => 20,
            'tax_id' => 'J-12345678-9', 'billing_email' => 'cobros@sol.test', 'whatsapp_number' => '04247673951', 'email' => 'hola@sol.test',
        ]);

        asCardsApp($owner)->putJson('/api/v1/business/identity', ['name' => 'Sol Nuevo'])->assertOk();
        asCardsApp($owner)->putJson('/api/v1/business/location', ['address' => 'Calle 9'])->assertOk();
        asCardsApp($owner)->putJson('/api/v1/business/appointments', ['appointment_capacity' => 3])->assertOk();
        asCardsApp($owner)->putJson('/api/v1/business/contact', ['email' => 'nuevo@sol.test'])->assertOk();
        asCardsApp($owner)->putJson('/api/v1/business/billing', ['billing_email' => 'otro@sol.test'])->assertOk();

        expect($owner->business->fresh())
            ->name->toBe('Sol Nuevo')->description->toBe('Pan de masa madre.')
            ->address->toBe('Calle 9')->city->toBe('Mérida')->has_premises->toBeTrue()
            ->appointments_enabled->toBeTrue()->appointment_capacity->toBe(3)->appointment_slot_minutes->toBe(30)->appointments_per_day->toBe(20)
            ->email->toBe('nuevo@sol.test')->whatsapp_number->toBe('04247673951')
            ->tax_id->toBe('J-12345678-9')->billing_email->toBe('otro@sol.test');
    });

    it('still clears a field when the body says null on purpose', function (): void {
        $owner = payingUser(business: ['description' => 'Algo.']);

        asCardsApp($owner)->putJson('/api/v1/business/identity', ['description' => null])->assertOk();

        expect($owner->business->fresh()->description)->toBeNull();
    });

    it('leaves the links alone when the body carries no list, and replaces them when it does', function (): void {
        $owner = payingUser();
        $network = SocialNetwork::factory()->create();

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [['social_network_id' => $network->id, 'url' => 'instagram.com/sol']]])->assertOk();
        asCardsApp($owner)->putJson('/api/v1/business/social', [])->assertOk();

        expect(asCardsApp($owner)->getJson('/api/v1/business/social')->json('data.rows'))->toHaveCount(1);

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => []])->assertOk();

        expect(asCardsApp($owner)->getJson('/api/v1/business/social')->json('data.rows'))->toBe([]);
    });

    it('never reads a card it does not have', function (): void {
        asCardsApp(payingUser())->putJson('/api/v1/business/inventada', ['name' => 'x'])->assertNotFound();
        asCardsApp(payingUser())->getJson('/api/v1/business/inventada')->assertNotFound();
    });

    it('splits a stored phone however it was typed, so the screen shows the real number', function (string $stored, string $dial, string $number): void {
        $venezuela = Country::factory()->create(['iso2' => 'VE', 'phone_code' => '58']);
        $owner = payingUser(business: ['whatsapp_number' => $stored, 'country_id' => $venezuela->id]);

        $shown = fieldOf(asCardsApp($owner)->getJson('/api/v1/business/contact')->json('data'), 'whatsapp_number')['value'];

        expect($shown)->toBe(['dial' => $dial, 'number' => $number]);
    })->with([
        'the web\'s own format' => ['+58 4247673951', '58', '4247673951'],
        'a plus and no space' => ['+584247673951', '58', '4247673951'],
        'digits with the country code' => ['584247673951', '58', '4247673951'],
        'a national number keeps its zero and the business\'s dial' => ['04247673951', '58', '04247673951'],
    ]);
});

describe('the social networks', function (): void {
    it('refuses a link that is no web address, and accepts a plain handle or a bare domain', function (): void {
        $owner = payingUser();
        $network = SocialNetwork::factory()->create();
        $put = fn (string $url) => asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [['social_network_id' => $network->id, 'url' => $url]]]);

        $put('javascript:alert(1)')->assertUnprocessable()->assertJsonValidationErrors('social.0.url');
        $put('data:text/html,hola')->assertUnprocessable()->assertJsonValidationErrors('social.0.url');
        $put('https://instagram.com/sol')->assertOk();
        $put('instagram.com/sol')->assertOk();
        $put('@sol')->assertOk();
    });

    it('lists the links in their public order and only the networks that can be picked', function (): void {
        $owner = payingUser();
        $first = SocialNetwork::factory()->create(['name' => 'Instagram', 'is_active' => true]);
        $second = SocialNetwork::factory()->create(['name' => 'Facebook', 'is_active' => true]);
        $off = SocialNetwork::factory()->create(['name' => 'Retirada', 'is_active' => false]);
        $owner->business->socialLinks()->create(['social_network_id' => $second->id, 'url' => 'https://facebook.com/sol', 'sort_order' => 1]);
        $owner->business->socialLinks()->create(['social_network_id' => $first->id, 'url' => 'https://instagram.com/sol', 'sort_order' => 0]);

        $card = asCardsApp($owner)->getJson('/api/v1/business/social')->assertOk()->json('data');

        expect(collect($card['rows'])->pluck('social_network_id')->all())->toBe([$first->id, $second->id])
            ->and(collect($card['networks'])->pluck('value'))->toContain($first->id)->not->toContain($off->id)
            ->and($card['words']['add'])->toBe(__('client.business.social.add'));
    });

    it('saves them in the order sent, drops the blank row and removes what left the screen', function (): void {
        $owner = payingUser();
        $first = SocialNetwork::factory()->create(['is_active' => true]);
        $second = SocialNetwork::factory()->create(['is_active' => true]);
        $owner->business->socialLinks()->create(['social_network_id' => $first->id, 'url' => 'https://a.test/x', 'sort_order' => 0]);

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [
            ['social_network_id' => $second->id, 'url' => 'https://b.test/y'],
            ['social_network_id' => null, 'url' => ''],
        ]])->assertOk();

        $links = $owner->business->socialLinks()->get();

        expect($links->pluck('social_network_id')->all())->toBe([$second->id]);
    });

    it('refuses what the web\'s card refuses: a repeated network, a half row and a network that does not exist', function (): void {
        $owner = payingUser();
        $network = SocialNetwork::factory()->create(['is_active' => true]);

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [
            ['social_network_id' => $network->id, 'url' => 'https://a.test/x'],
            ['social_network_id' => $network->id, 'url' => 'https://a.test/y'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('social.0.social_network_id');

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [['social_network_id' => $network->id, 'url' => '']]])
            ->assertUnprocessable()->assertJsonValidationErrors('social.0.url');

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => [['social_network_id' => 999999, 'url' => 'https://a.test/x']]])
            ->assertUnprocessable()->assertJsonValidationErrors('social.0.social_network_id');

        expect($owner->business->socialLinks()->count())->toBe(0);
    });

    it('never touches another business\'s links', function (): void {
        $owner = payingUser();
        $other = Business::factory()->create();
        $network = SocialNetwork::factory()->create(['is_active' => true]);
        $other->socialLinks()->create(['social_network_id' => $network->id, 'url' => 'https://otro.test/x', 'sort_order' => 0]);

        asCardsApp($owner)->putJson('/api/v1/business/social', ['social' => []])->assertOk();

        expect($other->socialLinks()->count())->toBe(1);
    });
});

describe('the logo', function (): void {
    it('is stored under the public disk and the file it replaces leaves it', function (): void {
        Storage::fake('public');
        $previous = UploadedFile::fake()->image('viejo.png')->store('logos', 'public');
        $owner = payingUser(business: ['logo_path' => $previous]);

        $response = asCardsApp($owner)->post('/api/v1/business/identity/logo', ['logo_file' => UploadedFile::fake()->image('nuevo.png')], ['Accept' => 'application/json'])->assertOk();

        $stored = $owner->business->fresh()->logo_path;

        expect($stored)->not->toBe($previous)->and($response->json('data.logo_url'))->toContain($stored);
        Storage::disk('public')->assertExists($stored);
        Storage::disk('public')->assertMissing($previous);
    });

    it('refuses what is not a PNG, JPG or WebP: a PDF and an SVG never reach the moderation', function (): void {
        Storage::fake('public');
        $owner = payingUser();

        asCardsApp($owner)->post('/api/v1/business/identity/logo', ['logo_file' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('logo_file');
        asCardsApp($owner)->post('/api/v1/business/identity/logo', ['logo_file' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('logo_file');
        asCardsApp($owner)->post('/api/v1/business/identity/logo', [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('logo_file');

        expect($owner->business->fresh()->logo_path)->toBeNull();
    });

    it('goes through the same moderation as on the web: an adult image is refused, flagged and never stored', function (): void {
        Storage::fake('public');
        Mail::fake();
        config()->set('ai.providers.openai.url', 'http://openai.test/v1');
        config()->set('ai.providers.openai.key', 'test-openai');
        app()->instance(ContentModeration::class, new ContentModeration);
        Http::fake(['http://openai.test/*' => Http::response(['results' => [['categories' => ['sexual' => true], 'category_scores' => ['sexual' => 0.6]]]])]);
        $owner = payingUser();

        asCardsApp($owner)->post('/api/v1/business/identity/logo', ['logo_file' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('logo_file');

        expect($owner->business->fresh()->logo_path)->toBeNull()
            ->and(ModerationFlag::query()->sole())->source->toBe('logo')->severity->not->toBe(ModerationSeverity::Clean);
        expect(Storage::disk('public')->allFiles('logos'))->toBe([]);
    });
});
