<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\CheckBusinessHours;
use App\Ai\Tools\GetBusinessContact;
use App\Ai\Tools\SearchBusinessKnowledge;
use App\Ai\Tools\SearchCatalog;
use App\Interfaces\Main\AssistantSkillTool;
use App\Jobs\EmbedCatalog;
use App\Models\AssistantSkill;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessHour;
use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use Carbon\CarbonImmutable;
use Database\Seeders\AssistantSkillSeeder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Providers\Tools\ToolSearch;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Assistant skills — exact data in a line, per trade, deferred when rare
|--------------------------------------------------------------------------
| The universal skills answer hours, catalog and contact from the real
| tables; a trade's own skills ride behind ToolSearch so every request
| does not pay for every trade.
*/

/** A trade-only skill standing in for the future ones (bookings, orders). */
class FakeBookingSkill implements AssistantSkillTool
{
    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return new static;
    }

    public function description(): string
    {
        return 'Reserva turnos.';
    }

    public function handle(Request $request): string
    {
        return 'ok';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

/** Same stand-in meaning space as the topics test: shared words share an axis. */
function skillVector(string $text): array
{
    $vector = array_fill(0, 1536, 0.0);
    $vector[str_contains(mb_strtolower($text), 'tiroid') ? 2 : 500 + (crc32($text) % 900)] = 1.0;

    return $vector;
}

beforeEach(function (): void {
    Queue::fake();

    $embedder = $this->mock(KnowledgeEmbedder::class);
    $embedder->shouldReceive('embed')->andReturnUsing(fn (array $texts): array => array_map(skillVector(...), $texts))->byDefault();
    $embedder->shouldReceive('embedOne')->andReturnUsing(fn (string $text): array => skillVector($text))->byDefault();
});

test('hours answer with today, the time and whether it is open now, in the business timezone', function (): void {
    $business = Business::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    BusinessHour::factory()->create(['business_id' => $business->id, 'day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00']);

    // Monday 10:30 in Buenos Aires.
    $this->travelTo(CarbonImmutable::parse('2026-09-21 13:30:00', 'UTC'));

    $answer = (string) (new CheckBusinessHours($business))->handle(new Request([]));

    expect($answer)->toContain('10:30')
        ->toContain('está abierto')
        ->toContain('08:00 a 12:00');
});

test('hours name every closed day instead of leaving it out', function (): void {
    // Left out, a plain Sunday was answered with "no pude confirmar" (ai-eval).
    $business = Business::factory()->create();
    BusinessHour::factory()->create(['business_id' => $business->id, 'day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00']);

    expect($business->scheduleLines())->toHaveCount(7)
        ->and($business->scheduleLines()[0])->toBe('Lunes: 08:00 a 12:00')
        ->and($business->scheduleLines()[6])->toBe('Domingo: cerrado');
});

test('hours resolve the weekday of an asked date in code', function (string $date, string $expected): void {
    $business = Business::factory()->create(['timezone' => 'America/Caracas']);
    BusinessHour::factory()->create(['business_id' => $business->id, 'day_of_week' => 1, 'opens_at' => '07:00', 'closes_at' => '12:00']);
    $this->travelTo(CarbonImmutable::parse('2026-10-02 16:30:00', 'UTC'));

    expect((string) (new CheckBusinessHours($business))->handle(new Request(['date' => $date])))->toContain($expected);
})->with([
    'a far Monday' => ['2026-10-12', 'El 12/10/2026 es Lunes. Horario de ese día: Lunes: 07:00 a 12:00'],
    'a closed Sunday' => ['2026-10-11', 'Horario de ese día: Domingo: cerrado'],
    'an impossible date' => ['2026-02-31', 'Fecha inválida'],
]);

test('contact answers from the profile, and says so when nothing is loaded', function (): void {
    $business = Business::factory()->create(['address' => 'Av. Siempre Viva 742', 'city' => 'Springfield', 'email' => null, 'web' => null, 'whatsapp_number' => null]);
    $empty = Business::factory()->create(['address' => null, 'city' => null, 'email' => null, 'web' => null, 'whatsapp_number' => null, 'has_premises' => null]);

    expect((string) (new GetBusinessContact($business))->handle(new Request([])))->toContain('Av. Siempre Viva 742, Springfield')
        ->and((string) (new GetBusinessContact($empty))->handle(new Request([])))->toContain('no cargó');
});

test('the catalog finds the item by meaning with its price, and only in this business', function (): void {
    $business = Business::factory()->create();
    $other = Business::factory()->create();
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Perfil tiroideo', 'price' => 16500, 'price_type' => 'fixed', 'prep_note' => 'Ayuno de 8 horas']);
    Service::factory()->create(['business_id' => $other->id, 'name' => 'Perfil tiroideo premium', 'price' => 99000, 'price_type' => 'fixed']);
    Product::factory()->create(['business_id' => $business->id, 'name' => 'Kit de recolección']);
    (new EmbedCatalog($business->id))->handle(app(KnowledgeEmbedder::class));
    (new EmbedCatalog($other->id))->handle(app(KnowledgeEmbedder::class));

    $tool = new SearchCatalog($business);

    expect((string) $tool->handle(new Request(['item' => 'el tiroideo'])))
        ->toContain('Perfil tiroideo')
        ->toContain('16500')
        ->toContain('Ayuno de 8 horas')
        ->not->toContain('premium')
        ->and((string) $tool->handle(new Request(['item' => 'resonancia'])))->toContain('No hay nada parecido')
        ->and((string) $tool->handle(new Request(['item' => ''])))->toContain('Servicios: Perfil tiroideo')->toContain('Productos: Kit de recolección');
});

test('every business gets the universal skills upfront, and only its trade gets the trade skill deferred', function (): void {
    $this->seed(AssistantSkillSeeder::class);
    config()->set('atendia.assistant.skills.bookings', FakeBookingSkill::class);

    $salon = BusinessActivity::factory()->create();
    $booking = AssistantSkill::query()->create(['key' => 'bookings', 'name' => 'Turnos', 'is_universal' => false]);
    $booking->activities()->attach($salon->id);

    $salonBusiness = Business::factory()->create();
    $salonBusiness->activities()->attach($salon->id, ['is_primary' => true]);
    $bakery = Business::factory()->create();

    $salonTools = (new AsistenteAtendia($salonBusiness))->tools();
    $bakeryTools = (new AsistenteAtendia($bakery))->tools();
    $deferred = collect($salonTools)->first(fn ($tool): bool => $tool instanceof ToolSearch);

    expect(collect($bakeryTools)->map(fn ($tool): string => $tool::class)->all())
        ->toBe([SearchCatalog::class, CheckBusinessHours::class, GetBusinessContact::class, SearchBusinessKnowledge::class])
        ->and($deferred)->not->toBeNull()
        ->and($deferred->tools[0])->toBeInstanceOf(FakeBookingSkill::class)
        ->and(collect($bakeryTools)->contains(fn ($tool): bool => $tool instanceof ToolSearch))->toBeFalse();
});

test('a switched-off skill leaves every assistant', function (): void {
    $this->seed(AssistantSkillSeeder::class);
    AssistantSkill::query()->where('key', 'contact')->update(['is_active' => false]);

    $tools = collect((new AsistenteAtendia(Business::factory()->create()))->tools());

    expect($tools->contains(fn ($tool): bool => $tool instanceof GetBusinessContact))->toBeFalse()
        ->and($tools->contains(fn ($tool): bool => $tool instanceof CheckBusinessHours))->toBeTrue();
});

test('with no timezone of its own a business keeps its country\'s clock', function (): void {
    $venezuela = Country::factory()->create(['iso2' => 'VE']);
    $business = Business::factory()->create(['timezone' => null, 'country_id' => $venezuela->id]);
    BusinessHour::factory()->create(['business_id' => $business->id, 'day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00']);

    // Monday 13:30 UTC is 09:30 in Caracas: open. Read in UTC it said closed.
    $this->travelTo(CarbonImmutable::parse('2026-09-21 13:30:00', 'UTC'));

    expect($business->localTimezone())->toBe('America/Caracas')
        ->and($business->isOpenNow())->toBeTrue()
        ->and((string) (new CheckBusinessHours($business))->handle(new Request([])))->toContain('09:30');
});

test('the catalog list says how many more it left out instead of passing as complete', function (): void {
    $business = Business::factory()->create();
    Service::factory()->count(32)->create(['business_id' => $business->id, 'is_active' => true]);

    expect((string) (new SearchCatalog($business))->handle(new Request(['item' => ''])))->toContain('y 2 más');
});
