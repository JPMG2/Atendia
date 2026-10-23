<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\DailyDigest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** One call as this person; Sanctum's guard remembers the first person it saw, so each call forgets it. */
function asDigestApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

it('lists the kept reads, the freshest day first', function (): void {
    $owner = payingUser();
    DailyDigest::factory()->for($owner->business)->create(['for_day' => '2026-10-21', 'messages' => 7]);
    DailyDigest::factory()->for($owner->business)->create(['for_day' => '2026-10-22', 'messages' => 12, 'contacts' => 5]);

    $rows = asDigestApp($owner)->getJson('/api/v1/digests')->assertOk()->json('data');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'day' => '2026-10-22',
            'messages' => 12,
            'contacts' => 5,
            'questions' => ['¿Atienden el sábado?', '¿Precio del perfil?'],
            'appointments_tomorrow' => 2,
        ])
        ->and($rows[1]['day'])->toBe('2026-10-21');
});

it('keeps to the last two weeks', function (): void {
    $owner = payingUser();

    foreach (range(1, 20) as $day) {
        DailyDigest::factory()->for($owner->business)->create(['for_day' => now()->subDays($day)->toDateString()]);
    }

    expect(asDigestApp($owner)->getJson('/api/v1/digests')->assertOk()->json('data'))->toHaveCount(14);
});

it('is empty when no day was ever mailed, as on the entry plan', function (): void {
    asDigestApp(payingUser())->getJson('/api/v1/digests')->assertOk()->assertJsonPath('data', []);
});

it('never shows the reads of another business', function (): void {
    $owner = payingUser();
    DailyDigest::factory()->for(Business::factory()->create())->create();

    asDigestApp($owner)->getJson('/api/v1/digests')->assertOk()->assertJsonPath('data', []);
});

it('is the owner\'s read: an agent of the business does not get it', function (): void {
    $owner = payingUser();
    DailyDigest::factory()->for($owner->business)->create();
    $agent = payingUser();
    $agent->forceFill(['business_id' => $owner->business_id])->save();
    $agent->syncRoles(['agent']);

    asDigestApp($agent->refresh())->getJson('/api/v1/digests')->assertForbidden();
});

it('needs a token', function (): void {
    test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/digests')->assertUnauthorized();
});
