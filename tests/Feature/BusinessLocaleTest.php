<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a business speaks the variant of its country, neutral when unmapped', function (string $iso2, string $locale): void {
    $business = Business::factory()->create(['country_id' => Country::factory()->create(['iso2' => $iso2])->id]);

    expect($business->locale())->toBe($locale);
})->with([
    'Argentina' => ['AR', 'es_AR'],
    'Venezuela' => ['VE', 'es_VE'],
    'Mexico' => ['MX', 'es'],
]);

test('speaking switches the locale only inside the callback', function (): void {
    $business = Business::factory()->create(['country_id' => Country::factory()->create(['iso2' => 'AR'])->id]);

    expect($business->speaking(fn (): string => app()->getLocale()))->toBe('es_AR')
        ->and(app()->getLocale())->toBe('es');
});
