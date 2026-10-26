<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\CountryHolidaySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

/** One call as this person from the install the account already used; Sanctum's guard remembers the first person it saw. */
function asProfileApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function profileOwner(array $business = []): User
{
    return payingUser([], $business);
}

describe('the region the hub speaks', function (): void {
    function appointmentsTitle(User $user, array $headers): ?string
    {
        $sections = asProfileApp($user)->withHeaders($headers)->getJson('/api/v1/business')->assertOk()->json('data.sections');

        return collect($sections)->firstWhere('key', 'appointments')['title'] ?? null;
    }

    it('says "Citas" to a Venezuelan visit whose phone only announces neutral Spanish', function (): void {
        $owner = profileOwner();

        expect(appointmentsTitle($owner, ['Accept-Language' => 'es-US', 'CF-IPCountry' => 'VE']))->toBe('Citas')
            ->and(appointmentsTitle($owner, ['Accept-Language' => 'es-419', 'CF-IPCountry' => 've']))->toBe('Citas');
    });

    it('keeps "Turnos" for a visit from a country without a variant', function (): void {
        $owner = profileOwner();

        expect(appointmentsTitle($owner, ['Accept-Language' => 'es-US', 'CF-IPCountry' => 'CO']))->toBe('Turnos')
            ->and(appointmentsTitle($owner, ['Accept-Language' => 'es-US', 'CF-IPCountry' => 'XX']))->toBe('Turnos')
            ->and(appointmentsTitle($owner, ['Accept-Language' => 'es-US']))->toBe('Turnos');
    });

    it('lets a regional phone setting win over the country of the visit', function (): void {
        $owner = profileOwner();

        expect(appointmentsTitle($owner, ['Accept-Language' => 'es-VE', 'CF-IPCountry' => 'AR']))->toBe('Citas')
            ->and(appointmentsTitle($owner, ['Accept-Language' => 'es-AR', 'CF-IPCountry' => 'VE']))->toBe('Turnos');
    });
});

describe('the profile hub', function (): void {
    it('lists the sections in the order of the web menu, with the web\'s own titles', function (): void {
        $data = asProfileApp(profileOwner())->getJson('/api/v1/business')->assertOk()->json('data');

        expect(collect($data['sections'])->pluck('key')->all())->toBe(['identity', 'location', 'hours', 'appointments', 'contact', 'social', 'billing', 'team'])
            ->and(collect($data['sections'])->pluck('title')->all())->toBe([
                'Identidad', 'Ubicación', 'Horarios de atención', 'Turnos', 'Contacto', 'Redes sociales', 'Moneda', __('menu.team'),
            ])
            ->and(collect($data['sections'])->pluck('screen')->all())->toBe([
                'my-business.identidad', 'my-business.ubicacion', 'my-business.horarios', 'my-business.turnos',
                'my-business.contacto', 'my-business.redes', 'my-business.facturacion', 'team',
            ])
            ->and($data['copy']['title'])->toBe('Mi negocio')
            ->and($data['copy']['empty'])->toBe('Sin completar');
    });

    it('says the web\'s meter: how finished the profile is and which piece is still missing', function (): void {
        $strength = asProfileApp(profileOwner())->getJson('/api/v1/business')->json('data.strength');

        expect($strength['percent'])->toBeInt()
            ->and($strength['title'])->toBe('Tu perfil, al '.$strength['percent'].'%')
            ->and(collect($strength['pieces'])->pluck('label')->all())->toBe(['Datos de contacto', 'Moneda', 'Horarios', 'Redes sociales'])
            ->and(collect($strength['pieces'])->pluck('section')->all())->toBe(['contact', 'billing', 'hours', 'social']);
    });

    it('draws a line for what the business holds and none for what it lacks', function (): void {
        $owner = profileOwner(['name' => 'Laboratorio Vida', 'address' => 'Av. Los Próceres 12', 'city' => 'Mérida', 'has_premises' => true, 'appointments_enabled' => true, 'appointment_slot_minutes' => 30]);

        $rows = collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->keyBy('key');

        expect($rows['identity']['summary'])->toBe('Laboratorio Vida')
            ->and($rows['location']['summary'])->toBe('Av. Los Próceres 12, Mérida')
            ->and($rows['appointments']['summary'])->toBe('Cada 30 minutos')
            ->and($rows['hours']['summary'])->toBeNull()
            ->and($rows['social']['summary'])->toBeNull();
    });

    it('says today\'s shifts on the business clock, or that it is closed today', function (): void {
        $owner = profileOwner();
        $today = now($owner->business->localTimezone())->dayOfWeek;
        $otherDay = ($today + 1) % 7;

        app(Tenant::class)->for((int) $owner->business_id, function () use ($owner, $today, $otherDay): void {
            foreach ([[$today, '08:00', '12:00'], [$today, '14:00', '17:00'], [$otherDay, '09:00', '10:00']] as [$day, $opens, $closes]) {
                (new BusinessHour)->forceFill(['business_id' => $owner->business_id, 'day_of_week' => $day, 'opens_at' => $opens, 'closes_at' => $closes])->save();
            }
        });

        $hours = collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'hours');

        expect($hours['summary'])->toBe('Hoy 08:00 – 12:00 · 14:00 – 17:00');

        app(Tenant::class)->for((int) $owner->business_id, fn () => BusinessHour::query()->where('day_of_week', $today)->delete());

        expect(collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'hours')['summary'])->toBe('Hoy cerrado');
    });

    it('counts the people of the panel against the plan, like "Mi plan"', function (): void {
        $team = collect(asProfileApp(profileOwner())->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'team');

        expect($team['summary'])->toMatch('/^\d+ de \d+ personas$/');
    });

    it('is the owner\'s: an agent is refused', function (): void {
        $owner = profileOwner();
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        asProfileApp($agent->refresh())->getJson('/api/v1/business')->assertForbidden();
    });

    it('never shows another business\'s data', function (): void {
        $other = Business::factory()->create(['name' => 'Otro negocio']);
        $owner = profileOwner(['name' => 'Mi negocio']);

        $identity = collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'identity');

        expect($identity['summary'])->toBe('Mi negocio')->and($other->name)->toBe('Otro negocio');
    });

    it('needs a signed-in install', function (): void {
        $this->getJson('/api/v1/business', apiDeviceHeaders())->assertUnauthorized();
    });
});

describe('the opening hours', function (): void {
    // Saving republishes the business card to its knowledge base; the indexing job (OpenAI) is not what is measured here.
    beforeEach(fn () => Queue::fake());

    /** The week as the app sends it: shifts keyed by day, 0 = Sunday. */
    function weekOf(array $byDay): array
    {
        return ['week' => $byDay];
    }

    function storedShifts(User $owner): array
    {
        return app(Tenant::class)->for((int) $owner->business_id, fn () => BusinessHour::query()->orderBy('day_of_week')->orderBy('opens_at')
            ->get(['day_of_week', 'opens_at', 'closes_at'])
            ->map(fn (BusinessHour $shift): string => $shift->day_of_week.' '.substr($shift->opens_at, 0, 5).'-'.substr($shift->closes_at, 0, 5))
            ->all());
    }

    it('serves the week Monday first, as the web paints it, with the web\'s words', function (): void {
        $owner = profileOwner();
        app(Tenant::class)->for((int) $owner->business_id, function () use ($owner): void {
            (new BusinessHour)->forceFill(['business_id' => $owner->business_id, 'day_of_week' => 0, 'opens_at' => '10:00:00', 'closes_at' => '14:00:00'])->save();
        });

        $data = asProfileApp($owner)->getJson('/api/v1/business/hours')->assertOk()->json('data');

        expect(collect($data['days'])->pluck('day')->all())->toBe([1, 2, 3, 4, 5, 6, 0])
            ->and(collect($data['days'])->pluck('name')->all())->toBe(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
            ->and($data['days'][6]['shifts'])->toBe([['opens_at' => '10:00', 'closes_at' => '14:00']])
            ->and($data['days'][0]['shifts'])->toBe([])
            ->and($data['copy']['title'])->toBe('Horarios de atención')
            ->and($data['copy']['add_shift'])->toBe('Agregar turno')
            ->and($data['copy']['leave']['title'])->toBe('¿Salir sin guardar?');
    });

    it('replaces the whole week through the web\'s Action and completes the profile piece', function (): void {
        $owner = profileOwner();

        $response = asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '09:00', 'closes_at' => '13:00'], ['opens_at' => '14:00', 'closes_at' => '18:00']],
            6 => [['opens_at' => '22:00', 'closes_at' => '02:00']],
        ]))->assertOk();

        expect($response->json('message'))->toBe('Negocio actualizado correctamente')
            ->and(storedShifts($owner))->toBe(['1 09:00-13:00', '1 14:00-18:00', '6 22:00-02:00'])
            ->and($response->json('data.days.0.shifts'))->toHaveCount(2);

        $pieces = collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.strength.pieces'))->keyBy('key');

        expect($pieces['schedule']['done'])->toBeTrue();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([2 => [['opens_at' => '08:00', 'closes_at' => '12:00']]]))->assertOk();

        expect(storedShifts($owner))->toBe(['2 08:00-12:00']);
    });

    it('refuses two shifts that step on each other, with the web\'s sentence, and saves nothing', function (): void {
        $owner = profileOwner();

        $response = asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '09:00', 'closes_at' => '13:00'], ['opens_at' => '12:00', 'closes_at' => '18:00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['week.1.1.opens_at']);

        expect($response->json('errors')['week.1.1.opens_at'][0])->toBe(__('client.business.hours.overlaps', ['shift' => '09:00–13:00']))
            ->and(storedShifts($owner))->toBe([]);
    });

    it('lets two shifts touch and lets a night shift close the next day', function (): void {
        $owner = profileOwner();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '09:00', 'closes_at' => '12:00'], ['opens_at' => '12:00', 'closes_at' => '18:00']],
            5 => [['opens_at' => '22:00', 'closes_at' => '02:00']],
        ]))->assertOk();

        expect(storedShifts($owner))->toBe(['1 09:00-12:00', '1 12:00-18:00', '5 22:00-02:00']);
    });

    it('refuses a night shift that runs into another shift of the same day', function (): void {
        asProfileApp(profileOwner())->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '22:00', 'closes_at' => '02:00'], ['opens_at' => '01:00', 'closes_at' => '03:00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['week.1.1.opens_at']);
    });

    it('refuses what is not an hour, equal times and a missing week', function (): void {
        $owner = profileOwner();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '9am', 'closes_at' => '13:00']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['week.1.0.opens_at']);
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '09:00', 'closes_at' => '09:00']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['week.1.0.closes_at']);
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '09:00']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['week.1.0.closes_at']);
        asProfileApp($owner)->putJson('/api/v1/business/hours', [])->assertUnprocessable()->assertJsonValidationErrors(['week']);

        expect(storedShifts($owner))->toBe([]);
    });

    it('refuses shifts sent as a keyed object instead of a list, with a 422 and not a crash', function (): void {
        $owner = profileOwner();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => ['a' => ['opens_at' => '09:00', 'closes_at' => '10:00'], 'b' => ['opens_at' => '09:30', 'closes_at' => '11:00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['week.1']);

        expect(storedShifts($owner))->toBe([]);
    });

    it('closes a day by leaving it empty and a week with nothing is a business always closed', function (): void {
        $owner = profileOwner();
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '09:00', 'closes_at' => '13:00']]]))->assertOk();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => []]))->assertOk();

        expect(storedShifts($owner))->toBe([]);
    });

    it('does nothing and says so when the week is the one already saved', function (): void {
        $owner = profileOwner();
        $week = weekOf([1 => [['opens_at' => '09:00', 'closes_at' => '13:00']]]);
        asProfileApp($owner)->putJson('/api/v1/business/hours', $week)->assertOk();
        $ids = app(Tenant::class)->for((int) $owner->business_id, fn () => BusinessHour::query()->pluck('id')->all());

        $response = asProfileApp($owner)->putJson('/api/v1/business/hours', $week)->assertOk();

        expect($response->json('message'))->toBe(__('notifications.no_changes'))
            ->and(app(Tenant::class)->for((int) $owner->business_id, fn () => BusinessHour::query()->pluck('id')->all()))->toBe($ids);
    });

    it('writes only the days and the two times: an injected id, tenant or day never reaches the table', function (): void {
        $owner = profileOwner();
        $other = Business::factory()->create();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '09:00', 'closes_at' => '13:00', 'id' => 1, 'business_id' => $other->id, 'day_of_week' => 4]],
            9 => [['opens_at' => '09:00', 'closes_at' => '13:00']],
        ]))->assertOk();

        expect(storedShifts($owner))->toBe(['1 09:00-13:00'])
            ->and(app(Tenant::class)->for((int) $other->id, fn () => BusinessHour::query()->count()))->toBe(0);
    });

    it('never touches another business\'s week', function (): void {
        $owner = profileOwner();
        $stranger = profileOwner();
        asProfileApp($stranger)->putJson('/api/v1/business/hours', weekOf([3 => [['opens_at' => '07:00', 'closes_at' => '08:00']]]))->assertOk();

        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '09:00', 'closes_at' => '13:00']]]))->assertOk();

        expect(storedShifts($stranger))->toBe(['3 07:00-08:00'])->and(storedShifts($owner))->toBe(['1 09:00-13:00']);
    });

    it('is the owner\'s: an agent can neither read nor write it', function (): void {
        $owner = profileOwner();
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);
        $agent->refresh();

        asProfileApp($agent)->getJson('/api/v1/business/hours')->assertForbidden();
        asProfileApp($agent)->putJson('/api/v1/business/hours', weekOf([1 => [['opens_at' => '09:00', 'closes_at' => '13:00']]]))->assertForbidden();

        expect(storedShifts($owner))->toBe([]);
    });

    it('needs a signed-in install', function (): void {
        $this->getJson('/api/v1/business/hours', apiDeviceHeaders())->assertUnauthorized();
        $this->putJson('/api/v1/business/hours', weekOf([]), apiDeviceHeaders())->assertUnauthorized();
    });
});

describe('what the assistant knows and whether the door is open', function (): void {
    beforeEach(fn () => Queue::fake());

    it('says the week as the assistant will say it, the very lines published to its knowledge', function (): void {
        $owner = profileOwner();
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([
            1 => [['opens_at' => '09:00', 'closes_at' => '13:00'], ['opens_at' => '14:00', 'closes_at' => '18:00']],
        ]))->assertOk();

        $lines = asProfileApp($owner)->getJson('/api/v1/business/hours')->json('data.assistant');

        expect($lines)->toHaveCount(7)
            ->and($lines[0])->toBe('Lunes: 09:00 a 13:00 y 14:00 a 18:00')
            ->and($lines[6])->toBe('Domingo: cerrado')
            ->and(app(Tenant::class)->for((int) $owner->business_id, fn () => KnowledgeDocument::query()->where('source_type', 'profile')->value('content')))
            ->toContain('Lunes: 09:00 a 13:00 y 14:00 a 18:00');
    });

    it('has nothing to say before any shift is saved', function (): void {
        expect(asProfileApp(profileOwner())->getJson('/api/v1/business/hours')->json('data.assistant'))->toBe([]);
    });

    it('tells the hub whether the door is open right now, by the clock the assistant uses', function (): void {
        $owner = profileOwner();
        $local = now($owner->business->localTimezone());
        $day = (int) $local->format('w');
        $hours = fn () => collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'hours');

        expect($hours()['badge'])->toBeNull();

        $this->travelTo($local->copy()->setTime(12, 0));
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([$day => [['opens_at' => '09:00', 'closes_at' => '18:00']]]))->assertOk();

        expect($hours()['badge'])->toBe(['label' => 'Abierto ahora', 'tone' => 'open']);

        $this->travelTo($local->copy()->setTime(20, 0));

        expect($hours()['badge'])->toBe(['label' => 'Cerrado ahora', 'tone' => 'closed']);
    });

    it('says closed on a day she loaded as a closure, whatever the weekly hours say', function (): void {
        $owner = profileOwner();
        $local = now($owner->business->localTimezone());
        $this->travelTo($local->copy()->setTime(12, 0));
        asProfileApp($owner)->putJson('/api/v1/business/hours', weekOf([(int) $local->format('w') => [['opens_at' => '09:00', 'closes_at' => '18:00']]]))->assertOk();
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => $local->format('Y-m-d')])->assertOk();

        $badge = collect(asProfileApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'hours')['badge'];

        expect($badge['tone'])->toBe('closed');
    });
});

describe('the days she does not open', function (): void {
    beforeEach(fn () => Queue::fake());

    function closuresOf(User $owner): array
    {
        return app(Tenant::class)->for((int) $owner->business_id, fn () => BusinessClosure::query()->orderBy('starts_on')
            ->get()
            ->map(fn (BusinessClosure $closure): string => $closure->starts_on->format('Y-m-d').'..'.$closure->ends_on->format('Y-m-d').' '.($closure->reason ?? '-'))
            ->all());
    }

    it('lists what is still ahead, with the web\'s words, and leaves out what already passed', function (): void {
        $owner = profileOwner();
        $business = $owner->business;
        $business->closures()->create(['starts_on' => now()->subMonth()->format('Y-m-d'), 'ends_on' => now()->subMonth()->format('Y-m-d'), 'reason' => 'Ya pasó']);
        $business->closures()->create(['starts_on' => '2099-12-24', 'ends_on' => '2099-12-26', 'reason' => 'Navidad']);
        $business->closures()->create(['starts_on' => '2099-12-31', 'ends_on' => '2099-12-31', 'opens_at' => '09:00', 'closes_at' => '12:00']);

        $data = asProfileApp($owner)->getJson('/api/v1/business/closures')->assertOk()->json('data');

        expect(collect($data['closures'])->pluck('label')->all())->toBe(['24/12/2099 – 26/12/2099', '31/12/2099'])
            ->and($data['closures'][0]['reason'])->toBe('Navidad')
            ->and($data['closures'][0]['full_day'])->toBeTrue()
            ->and($data['closures'][1]['hours'])->toBe('09:00 – 12:00')
            ->and($data['copy']['title'])->toBe('Días que no abres')
            ->and($data['copy']['from'])->toBe('Desde');
    });

    it('loads a day, a range and special hours through the web\'s piece', function (): void {
        $owner = profileOwner();

        $one = asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-03-01', 'reason' => '  Vacaciones  '])->assertOk();
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-01..2099-04-05'])->assertOk();
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-05-01', 'opens_at' => '09:00', 'closes_at' => '12:00'])->assertOk();

        expect($one->json('message'))->toBe('Cierre agregado')
            ->and($one->json('kind'))->toBe('success')
            ->and(closuresOf($owner))->toBe(['2099-03-01..2099-03-01 Vacaciones', '2099-04-01..2099-04-05 -', '2099-05-01..2099-05-01 -']);
    });

    it('says so and writes nothing when the days are already loaded', function (): void {
        $owner = profileOwner();
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-01..2099-04-05'])->assertOk();

        $again = asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-03'])->assertOk();

        expect($again->json('message'))->toBe('Ya tienes un cierre cargado en esos días.')
            ->and($again->json('kind'))->toBe('info')
            ->and(closuresOf($owner))->toHaveCount(1);
    });

    it('refuses what is not a day, a range that ends before it starts and half a special hour', function (): void {
        $owner = profileOwner();

        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-13-45'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-02-30'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-05..2099-04-01'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => 'mañana'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', [])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        // Malformed JSON is a refusal too, never a crash: an array where a string goes, and years no calendar of hers reaches.
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => ['a']])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '0000-01-01'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '1999-12-31'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-12-31..2101-01-01'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '9999-12-31'])->assertUnprocessable()->assertJsonValidationErrors(['range']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-01', 'opens_at' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors(['closes_at']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-01', 'opens_at' => '09:00', 'closes_at' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors(['closes_at']);
        asProfileApp($owner)->postJson('/api/v1/business/closures', ['range' => '2099-04-01', 'reason' => str_repeat('x', 81)])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        expect(closuresOf($owner))->toBe([]);
    });

    it('removes a closure of its own business and never one of another', function (): void {
        $owner = profileOwner();
        $stranger = profileOwner();
        $mine = $owner->business->closures()->create(['starts_on' => '2099-03-01', 'ends_on' => '2099-03-01']);
        $theirs = $stranger->business->closures()->create(['starts_on' => '2099-03-01', 'ends_on' => '2099-03-01']);

        asProfileApp($owner)->deleteJson("/api/v1/business/closures/{$theirs->id}")->assertNotFound();
        $removed = asProfileApp($owner)->deleteJson("/api/v1/business/closures/{$mine->id}")->assertOk();

        expect($removed->json('message'))->toBe('Cierre quitado')
            ->and(closuresOf($owner))->toBe([])
            ->and(closuresOf($stranger))->toHaveCount(1);
    });

    it('loads the national holidays of her country in one tap, and twice adds nothing', function (): void {
        $owner = profileOwner();
        $owner->business->country->forceFill(['code' => 'ARG'])->save();
        (new CountryHolidaySeeder)->run();

        $listed = asProfileApp($owner)->getJson('/api/v1/business/closures')->json('data.holidays');
        $first = asProfileApp($owner)->postJson('/api/v1/business/closures/holidays')->assertOk();
        $loaded = count(closuresOf($owner));
        $second = asProfileApp($owner)->postJson('/api/v1/business/closures/holidays')->assertOk();

        expect($listed['count'])->toBeGreaterThan(0)
            ->and($listed['label'])->toContain('feriados')
            ->and($first->json('kind'))->toBe('success')
            ->and($loaded)->toBeGreaterThan(0)
            ->and(implode('|', closuresOf($owner)))->toContain('Navidad')
            ->and($second->json('message'))->toBe('Ya tenías cargados todos los feriados que vienen.')
            ->and(count(closuresOf($owner)))->toBe($loaded)
            // Like the web, the button stays while the country has holidays ahead; pressing it again only says they are loaded.
            ->and($second->json('data.holidays.count'))->toBe($listed['count']);
    });

    it('offers no holidays button when there is nothing to add', function (): void {
        expect(asProfileApp(profileOwner())->getJson('/api/v1/business/closures')->json('data.holidays'))->toBeNull();
    });

    it('tells the people holding an hour on a closed day, frees the hour, and counts them on the list', function (): void {
        $whatsapp = mock(EvolutionApi::class);
        // From the business's own number, never the platform's.
        config()->set('services.evolution.instance', 'aliadito-plataforma');
        $whatsapp->shouldReceive('sendText')->once()->with('business-1', '5493415557788', Mockery::type('string'), Mockery::any())->andReturn('MSG-1');
        app()->instance(EvolutionApi::class, $whatsapp);

        $owner = profileOwner();
        $business = $owner->business;
        $business->update(['whatsapp_instance' => 'business-1', 'whatsapp_connected_at' => now()]);
        $customer = Customer::factory()->create(['business_id' => $business->id, 'phone' => '5493415557788']);
        $closure = $business->closures()->create(['starts_on' => '2099-12-24', 'ends_on' => '2099-12-24', 'reason' => 'Nochebuena']);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2099-12-24 10:00', $business->localTimezone()),
            'ends_at' => CarbonImmutable::parse('2099-12-24 11:00', $business->localTimezone()),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $listed = asProfileApp($owner)->getJson('/api/v1/business/closures')->json('data.closures.0');
        $told = asProfileApp($owner)->postJson("/api/v1/business/closures/{$closure->id}/tell")->assertOk();

        expect($listed['booked'])->toBe(1)
            ->and($listed['booked_label'])->toBe('1 turno reservado')
            ->and($told->json('message'))->toBe('Avisamos a 1 persona y su turno quedó cancelado')
            ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    });

    it('refuses to tell while the business\'s own WhatsApp is down, and cancels nothing', function (): void {
        $whatsapp = mock(EvolutionApi::class);
        $whatsapp->shouldReceive('sendText')->never();
        app()->instance(EvolutionApi::class, $whatsapp);

        $owner = profileOwner();
        $business = $owner->business;
        $business->update(['whatsapp_instance' => 'business-1', 'whatsapp_connected_at' => null]);
        $customer = Customer::factory()->create(['business_id' => $business->id, 'phone' => '5493415557788']);
        $closure = $business->closures()->create(['starts_on' => '2099-12-24', 'ends_on' => '2099-12-24']);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2099-12-24 10:00', $business->localTimezone()),
            'ends_at' => CarbonImmutable::parse('2099-12-24 11:00', $business->localTimezone()),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = asProfileApp($owner)->postJson("/api/v1/business/closures/{$closure->id}/tell")->assertUnprocessable();

        expect($response->json('message'))->toBe(__('client.business.hours.closure_line_down'))
            ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
    });

    it('tells nobody of another business', function (): void {
        $whatsapp = mock(EvolutionApi::class);
        $whatsapp->shouldReceive('sendText')->never();
        app()->instance(EvolutionApi::class, $whatsapp);

        $owner = profileOwner();
        $theirs = profileOwner()->business->closures()->create(['starts_on' => '2099-12-24', 'ends_on' => '2099-12-24']);

        asProfileApp($owner)->postJson("/api/v1/business/closures/{$theirs->id}/tell")->assertNotFound();
    });

    it('is the owner\'s: an agent can neither read, load, tell nor remove', function (): void {
        $owner = profileOwner();
        $closure = $owner->business->closures()->create(['starts_on' => '2099-03-01', 'ends_on' => '2099-03-01']);
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);
        $agent->refresh();

        asProfileApp($agent)->getJson('/api/v1/business/closures')->assertForbidden();
        asProfileApp($agent)->postJson('/api/v1/business/closures', ['range' => '2099-04-01'])->assertForbidden();
        asProfileApp($agent)->postJson('/api/v1/business/closures/holidays')->assertForbidden();
        asProfileApp($agent)->postJson("/api/v1/business/closures/{$closure->id}/tell")->assertForbidden();
        asProfileApp($agent)->deleteJson("/api/v1/business/closures/{$closure->id}")->assertForbidden();

        expect(closuresOf($owner))->toHaveCount(1);
    });

    it('needs a signed-in install', function (): void {
        $this->getJson('/api/v1/business/closures', apiDeviceHeaders())->assertUnauthorized();
        $this->postJson('/api/v1/business/closures', ['range' => '2099-04-01'], apiDeviceHeaders())->assertUnauthorized();
    });
});

describe('the menu', function (): void {
    it('offers "Mi negocio" first, as the web menu does, as the one screen the app has for it', function (): void {
        $menu = asProfileApp(profileOwner())->getJson('/api/v1/menu')->assertOk()->json('data');

        expect(collect($menu)->pluck('label')->all())->toBe(['Mi negocio', 'Catálogo', 'Conversaciones', 'Clientes', 'Agenda', 'WhatsApp', 'Plan y pagos'])
            ->and($menu[0]['screen'])->toBe('my-business')
            ->and($menu[0]['icon'])->toBe('store')
            // The profile's sections are the hub's rows, served by GET /business, not buttons of the menu.
            ->and($menu[0]['children'])->toBe([]);
    });

    it('does not offer "Mi negocio" to an agent', function (): void {
        $owner = profileOwner();
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        $labels = collect(asProfileApp($agent->refresh())->getJson('/api/v1/menu')->json('data'))->pluck('label')->all();

        expect($labels)->not->toContain('Mi negocio');
    });
});
