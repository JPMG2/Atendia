<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

describe('the menu', function (): void {
    it('offers "Mi negocio" first, as the web menu does, as the one screen the app has for it', function (): void {
        $menu = asProfileApp(profileOwner())->getJson('/api/v1/menu')->assertOk()->json('data');

        expect(collect($menu)->pluck('label')->all())->toBe(['Mi negocio', 'Conversaciones', 'WhatsApp', 'Plan y pagos'])
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
