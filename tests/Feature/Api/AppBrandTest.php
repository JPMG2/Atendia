<?php

declare(strict_types=1);

use App\Enums\PlatformEventKind;
use App\Models\Company;
use App\Models\PlatformEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;

uses(DatabaseTransactions::class);

/** The app opening: `me`, with the name its build says. Sanctum remembers the first person, so forget it each time. */
function openAppBuiltAs(?string $built): void
{
    app('auth')->forgetGuards();
    $user = test()->brandUser ??= payingUser();
    $headers = apiDeviceHeaders() + ($built === null ? [] : ['X-App-Brand' => $built]);

    test()->withToken(appInstallToken($user))->withHeaders($headers)->getJson('/api/v1/me')->assertOk();
}

function staleBrandLines(): Collection
{
    return PlatformEvent::query()->where('kind', PlatformEventKind::AppBrandStale->value)->get();
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->brandUser = null;
});

it('tells anyone the platform name, as the row the web reads says it', function (): void {
    Company::factory()->create(['brand_name' => 'Conversa']);

    $this->getJson('/api/v1/brand')->assertOk()->assertExactJson(['data' => ['name' => Company::brand()]]);
});

it('raises one line when an install carries a name the web no longer uses, and counts it again', function (): void {
    Company::factory()->create(['brand_name' => 'Conversa']);

    openAppBuiltAs('Aliadito');
    openAppBuiltAs('Aliadito');

    $line = staleBrandLines()->sole();

    expect($line->occurrences)->toBe(2)
        ->and($line->detail)->toBe(__('platform_events.app_brand_stale_detail', ['built' => 'Aliadito', 'web' => 'Conversa']));
});

it('says nothing when the install matches the web, whatever its case, or when it does not say', function (): void {
    Company::factory()->create(['brand_name' => 'Conversa']);

    openAppBuiltAs('conversa');
    openAppBuiltAs(null);

    expect(staleBrandLines())->toBeEmpty();
});

it('reads a name with an accent as the app sends it, URL-encoded', function (): void {
    Company::factory()->create(['brand_name' => 'Conversá']);

    openAppBuiltAs(rawurlencode('Conversá'));

    expect(staleBrandLines())->toBeEmpty();
});

it('falls back to the configured name while nobody has saved the company', function (): void {
    Company::query()->delete();

    $this->getJson('/api/v1/brand')->assertOk()->assertJsonPath('data.name', (string) config('app.name'));
});
