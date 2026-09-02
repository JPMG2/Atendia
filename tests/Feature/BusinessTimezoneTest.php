<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business's timezone
|--------------------------------------------------------------------------
| Messages must land in the RECIPIENT's daytime, and a country is not enough
| to know it: Mexico spans three zones, Brazil four. The zone belongs to the
| business, as an IANA identifier the scheduler can hand straight to Carbon.
*/

test('a business declares its own timezone, since a country can span several', function (): void {
    $business = Business::factory()->create(['timezone' => 'America/Mexico_City']);

    expect($business->refresh()->timezone)->toBe('America/Mexico_City');
});

test('the timezone is optional until the business picks one', function (): void {
    // Nullable on purpose: existing businesses have none, and the messaging
    // layer falls back to a sensible default while it stays empty.
    expect(Business::factory()->create()->timezone)->toBeNull();
});

test('the zones to offer come from PHP itself, not from a catalog of our own', function (): void {
    // Pinning the design: no timezones table to maintain. The country's ISO
    // code keys into the list the runtime already updates on its own.
    $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'AR');

    expect($zones)->toContain('America/Argentina/Buenos_Aires');
});
