<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Professional;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Profesionales" over the API
|--------------------------------------------------------------------------
| Read-only: the phone says what the web's list says, in the web's words.
| Nothing is written from here, so no write route exists to test.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->setLocale('es');
});

function asProfessionalsApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function professionalsOwner(): User
{
    $owner = payingUser();
    $owner->business->services()->create(['name' => 'Corte', 'duration_minutes' => 30, 'is_bookable' => true]);

    return $owner;
}

it('says the web list: each person with their hours, services, ceiling and what is still to come', function (): void {
    $owner = professionalsOwner();
    $ana = $owner->business->professionals()->create(['name' => 'Ana Soto', 'appointments_per_day' => 8]);
    $ana->services()->attach($owner->business->services()->pluck('id'));
    $owner->business->professionals()->create(['name' => 'Luis Paz', 'is_active' => false]);
    Appointment::factory()->count(2)->create(['business_id' => $owner->business_id, 'professional_id' => $ana->id]);

    $data = asProfessionalsApp($owner)->getJson('/api/v1/professionals')->assertOk()->json('data');

    expect($data['copy']['title'])->toBe(__('professionals.title'))
        ->and($data['copy']['sub'])->toBe(__('professionals.sub'))
        ->and($data['copy']['needs_services'])->toBeNull()
        ->and(collect($data['professionals'])->pluck('name')->all())->toBe(['Ana Soto', 'Luis Paz'])
        ->and($data['professionals'][0])->toMatchArray([
            'inactive' => null,
            'hours' => __('professionals.list.business_hours'),
            'services' => '1 servicio',
            'limits' => __('professionals.list.limits', ['per_day' => 8]),
            'upcoming' => '2 turnos por venir',
        ])
        ->and($data['professionals'][1])->toMatchArray([
            'inactive' => __('professionals.list.inactive'),
            'services' => 'Sin servicios',
            'limits' => null,
            'upcoming' => null,
        ]);
});

it('tells the owner when no service takes a booking yet, and nothing when nobody is loaded', function (): void {
    $owner = payingUser();

    $data = asProfessionalsApp($owner)->getJson('/api/v1/professionals')->assertOk()->json('data');

    expect($data['professionals'])->toBe([])
        ->and($data['copy']['needs_services'])->toBe(__('professionals.needs_services.body'))
        ->and($data['copy']['empty_title'])->toBe(__('professionals.list.empty_title'));
});

it('says "cita" where Venezuela does, in the same words the web screen prints', function (): void {
    expect(trans_choice('professionals.list.upcoming', 1, ['count' => 1], 'es_VE'))->toBe('1 cita por venir')
        ->and(trans_choice('professionals.list.upcoming', 3, ['count' => 3], 'es_VE'))->toBe('3 citas por venir')
        ->and(trans_choice('professionals.list.upcoming', 3, ['count' => 3], 'es'))->toBe('3 turnos por venir');
});

it('never shows a professional of another business', function (): void {
    $owner = professionalsOwner();
    $owner->business->professionals()->create(['name' => 'Mía']);
    Professional::factory()->create(['business_id' => Business::factory()->create()->id, 'name' => 'Ajena']);

    $names = asProfessionalsApp($owner)->getJson('/api/v1/professionals')->json('data.professionals.*.name');

    expect($names)->toBe(['Mía']);
});

it('is the owner\'s: an agent is refused', function (): void {
    $owner = professionalsOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    asProfessionalsApp($agent->refresh())->getJson('/api/v1/professionals')->assertForbidden();
});

it('is not let in without a session', function (): void {
    $this->getJson('/api/v1/professionals')->assertUnauthorized();
});

it('offers no way to write: the phone only looks', function (): void {
    $owner = professionalsOwner();

    asProfessionalsApp($owner)->postJson('/api/v1/professionals', ['name' => 'X'])->assertStatus(405);
    asProfessionalsApp($owner)->putJson('/api/v1/professionals/1', ['name' => 'X'])->assertStatus(404);
    asProfessionalsApp($owner)->deleteJson('/api/v1/professionals/1')->assertStatus(404);
});

it('adds the row to the app hub with the web\'s own summary', function (): void {
    $owner = professionalsOwner();
    $owner->business->professionals()->create(['name' => 'Ana']);
    $owner->business->professionals()->create(['name' => 'Luis']);

    $row = collect(asProfessionalsApp($owner)->getJson('/api/v1/business')->json('data.sections'))->firstWhere('key', 'professionals');

    expect($row['title'])->toBe(__('menu.professionals'))
        ->and($row['screen'])->toBe('professionals')
        ->and($row['summary'])->toBe('2 profesionales');
});
