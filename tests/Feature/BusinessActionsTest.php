<?php

declare(strict_types=1);

use App\Actions\Business\SaveBusinessConnection;
use App\Actions\Business\SaveBusinessIdentity;
use App\Actions\Business\SaveBusinessProducts;
use App\Actions\Business\SaveBusinessServices;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Country;
use App\Models\Province;
use App\Models\SuggestedService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business save Actions — the shared sockets
|--------------------------------------------------------------------------
| Each slice of the tenant persists through ONE Action so every screen that
| writes it (the wizard, the profile cards) shares the exact same code.
| These tests exercise the Actions directly: what a second caller gets.
*/

function identityPayload(): array
{
    $country = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);
    $activity = BusinessActivity::factory()->create();

    return [
        'name' => 'La Esquina',
        'country_id' => $country->id,
        'province_id' => $province->id,
        'activity' => $activity->code,
    ];
}

test('the identity action creates the business, hangs the user off it and syncs the primary activity', function (): void {
    $user = User::factory()->create()->refresh();
    $payload = identityPayload();

    $business = app(SaveBusinessIdentity::class)->handle($user, $payload);

    expect($business->name)->toBe('La Esquina')
        ->and($business->billing_email)->toBe($user->email)
        ->and($user->fresh()->business_id)->toBe($business->id)
        ->and($business->primaryActivity()?->code)->toBe($payload['activity']);
});

test('a second identity save updates the same row and keeps the billing email', function (): void {
    $user = User::factory()->create()->refresh();
    $payload = identityPayload();

    $first = app(SaveBusinessIdentity::class)->handle($user, $payload);
    $second = app(SaveBusinessIdentity::class)->handle($user->fresh(), [...$payload, 'name' => 'La Otra Esquina']);

    expect($second->id)->toBe($first->id)
        ->and(Business::query()->count())->toBe(1)
        ->and($second->name)->toBe('La Otra Esquina')
        ->and($second->billing_email)->toBe($user->email);
});

test('the connection action writes only its slice', function (): void {
    $business = Business::factory()->create(['name' => 'La Esquina']);

    app(SaveBusinessConnection::class)->handle($business, [
        'whatsapp_number' => '+58 412 5551234',
        'fallback_whatsapp_number' => '+58 412 5555678',
        'email' => 'hola@esquina.com',
        'name' => 'ignorada',
    ]);

    expect($business->fresh())
        ->whatsapp_number->toBe('+58 412 5551234')
        ->email->toBe('hola@esquina.com')
        ->name->toBe('La Esquina');
});

test('the services action reconciles the list and types the names the suggestions know', function (): void {
    $business = Business::factory()->create();
    $suggested = SuggestedService::factory()->create(['name' => 'Corte de pelo']);

    $changed = app(SaveBusinessServices::class)->handle($business, ['Corte de pelo', 'Manicure']);

    expect($changed)->toBeTrue()
        ->and($business->serviceNames())->toBe(['Corte de pelo', 'Manicure'])
        ->and($business->services()->firstWhere('name', 'Corte de pelo')->service_type_id)
        ->toBe($suggested->service_type_id);
});

test('the products action drops only names the screen knew, so imported rows survive', function (): void {
    $business = Business::factory()->create();
    app(SaveBusinessProducts::class)->handle($business, ['Champú', 'Acondicionador']);

    // The screen only ever showed 'Champú': dropping it must not touch the rest.
    $changed = app(SaveBusinessProducts::class)->handle($business, [], ['Champú']);

    expect($changed)->toBeTrue()
        ->and($business->productNames())->toBe(['Acondicionador']);
});
