<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business's minimal location
|--------------------------------------------------------------------------
| Country + province is all the sign-up asks: the province is what pins the
| timezone without asking for an IANA name. The street address is optional
| and belongs to the client panel's business module, not here.
*/

test('a business stores the province of its minimal location', function (): void {
    $province = Province::factory()->create();

    $business = Business::factory()->create([
        'country_id' => $province->country_id,
        'province_id' => $province->id,
    ]);

    expect($business->refresh()->province->name)->toBe($province->name);
});

test('the province stays optional until the wizard asks for it', function (): void {
    expect(Business::factory()->create()->province_id)->toBeNull();
});

test('the wizard business step cascades provinces from the chosen country', function (): void {
    $country = Country::factory()->create();
    $mine = Province::factory()->create(['country_id' => $country->id]);
    $foreign = Province::factory()->create();

    Livewire::test('business.step-business')
        ->set('form.data.country_id', $country->id)
        ->assertSee($mine->name)
        ->assertDontSee($foreign->name);
});

test('changing the country empties the chosen province', function (): void {
    $province = Province::factory()->create();
    $other = Country::factory()->create();

    Livewire::test('business.step-business')
        ->set('form.data.country_id', $province->country_id)
        ->set('form.data.province_id', $province->id)
        ->set('form.data.country_id', $other->id)
        ->assertSet('form.data.province_id', null);
});
