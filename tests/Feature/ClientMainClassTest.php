<?php

declare(strict_types=1);

use App\Classes\Main\Client;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Province;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Client — the main class every screen talks to
|--------------------------------------------------------------------------
| Object composition: Client composes the profile pieces and owns the one
| view no piece can give alone (the profile meter). Rebuilt per request,
| so it works the same from the client blades, the wizard or the API.
*/

function clientWithBusiness(array $attributes = []): array
{
    $business = Business::factory()->create($attributes);
    $user = User::factory()->create(['business_id' => $business->id])->refresh();

    return [Client::for($user), $business];
}

test('a user without a business composes only the personal data piece', function (): void {
    $client = Client::for(User::factory()->create()->refresh());

    expect($client->personalData()->data()->name)->toBe('')
        ->and($client->taxDetails())->toBeNull()
        ->and($client->schedule())->toBeNull()
        ->and($client->socialMedia())->toBeNull();
});

test('a user with a business gets every piece, each reading its own slice', function (): void {
    [$client, $business] = clientWithBusiness(['tax_id' => 'J-12345678-9']);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);
    SocialLink::factory()->for($business, 'linkable')->create();

    expect($client->personalData()->data()->name)->toBe($business->name)
        ->and($client->taxDetails()->data()['tax_id'])->toBe('J-12345678-9')
        ->and($client->schedule()->week()[1])->toHaveCount(1)
        ->and($client->schedule()->week()[0])->toBe([])
        ->and($client->socialMedia()->links())->toHaveCount(1);
});

test('personal data saves through the same identity action the wizard uses', function (): void {
    $user = User::factory()->create()->refresh();
    $country = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);
    $activity = BusinessActivity::factory()->create();

    $business = Client::for($user)->personalData()->saveIdentity([
        'name' => 'La Esquina',
        'country_id' => $country->id,
        'province_id' => $province->id,
        'activity' => $activity->code,
    ]);

    expect($user->fresh()->business_id)->toBe($business->id)
        ->and($business->primaryActivity()?->code)->toBe($activity->code);
});

test('the connection save refuses to invent a business', function (): void {
    $client = Client::for(User::factory()->create()->refresh());

    expect($client->personalData()->saveConnection(['email' => 'hola@esquina.com']))->toBeNull();
});

test('the tax details piece saves its slice and completes with a chosen currency', function (): void {
    [$client, $business] = clientWithBusiness();

    expect($client->taxDetails()->isComplete())->toBeFalse();

    $client->taxDetails()->save([
        'currency_id' => Currency::factory()->create()->id,
        'tax_condition_id' => TaxCondition::factory()->create()->id,
        'tax_id' => 'J-12345678-9',
        'name' => 'ignorada',
    ]);

    expect($client->taxDetails()->isComplete())->toBeTrue()
        ->and($business->fresh())
        ->tax_id->toBe('J-12345678-9')
        ->name->toBe($business->name);
});

test('the schedule piece replaces the week whole, so no stale shift survives', function (): void {
    [$client, $business] = clientWithBusiness();
    $business->hours()->create(['day_of_week' => 3, 'opens_at' => '08:00', 'closes_at' => '12:00']);

    $client->schedule()->save([
        1 => [['opens_at' => '09:00', 'closes_at' => '13:00'], ['opens_at' => '16:00', 'closes_at' => '20:00']],
        0 => [],
    ]);

    $week = $client->schedule()->week();

    expect($week[1])->toHaveCount(2)
        ->and($week[3])->toBe([])
        ->and($client->schedule()->isComplete())->toBeTrue();
});

test('the social media piece upserts by network, orders by screen position and drops what left', function (): void {
    [$client, $business] = clientWithBusiness();
    $instagram = SocialNetwork::factory()->create();
    $facebook = SocialNetwork::factory()->create();
    SocialLink::factory()->for($business, 'linkable')->create(['social_network_id' => $facebook->id]);

    $changed = $client->socialMedia()->save([
        ['social_network_id' => $instagram->id, 'url' => 'https://instagram.com/esquina'],
    ]);

    $links = $client->socialMedia()->links();

    expect($changed)->toBeTrue()
        ->and($links)->toHaveCount(1)
        ->and($links->first()->social_network_id)->toBe($instagram->id)
        ->and($links->first()->sort_order)->toBe(0);
});

test('the profile meter counts every piece as missing while the business is unborn', function (): void {
    $client = Client::for(User::factory()->create()->refresh());

    expect($client->profileStrength())->toBe([
        'done' => 0,
        'total' => 4,
        'missing' => ['personal_data', 'tax_details', 'schedule', 'social_media'],
    ]);
});

test('the profile meter fills as the pieces do, and names what is left', function (): void {
    [$client, $business] = clientWithBusiness([
        'whatsapp_number' => '+58 412 5551234',
        'fallback_whatsapp_number' => '+58 412 5555678',
        'email' => 'hola@esquina.com',
        'currency_id' => Currency::factory()->create()->id,
    ]);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);

    expect($client->profileStrength())->toBe([
        'done' => 3,
        'total' => 4,
        'missing' => ['social_media'],
    ]);
});
