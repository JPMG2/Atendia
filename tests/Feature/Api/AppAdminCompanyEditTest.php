<?php

declare(strict_types=1);

use App\Mail\DeviceChallengeCode;
use App\Mail\NewCompany;
use App\Models\Business;
use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The company edited from the app
|--------------------------------------------------------------------------
| The phone saves through the same piece as the web form (`CompanyEditor`):
| same rules, same words, same chained lists. Logos stay on the web.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function companyEditToken(string $role = 'admin'): string
{
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->syncRoles([$role]);

    $id = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
        ->assertStatus(202)->json('challenge.id');

    $code = '';
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code])->assertOk()->json('token');
}

function companyEditCall(string $token, string $method, string $path, array $body = []): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->json($method, '/api/v1/admin/company'.$path, $body);
}

/** @return array{company: Company, country: Country, province: Province, region: Region, tax: TaxCondition} */
function companyEditPlace(): array
{
    $country = Country::factory()->create(['name' => 'Venezuela Qzv', 'is_active' => true]);
    $province = Province::factory()->for($country)->create(['name' => 'Mérida Qzv', 'is_active' => true]);
    $region = Region::factory()->for($province)->create(['name' => 'Spinetti Qzv', 'is_active' => true]);
    $tax = TaxCondition::factory()->create(['country_id' => $country->id, 'is_active' => true]);
    $company = Company::factory()->create([
        'legal_name' => 'Yuca Qzv SA', 'address' => 'Calle 24 Qzv', 'region_id' => $region->id, 'tax_condition_id' => $tax->id,
        'tax_id' => '12352659', 'logo_path_light' => 'logos/light.svg', 'email' => 'soporte@qzv.test',
    ]);

    return compact('company', 'country', 'province', 'region', 'tax');
}

test('the main step comes in the web form words, with today values and the chained lists, and no logo', function (): void {
    ['region' => $region, 'province' => $province, 'country' => $country, 'tax' => $tax] = companyEditPlace();

    $data = companyEditCall(companyEditToken(), 'GET', '/main')->assertOk()->json('data');
    $fields = collect($data['fields'])->keyBy('key');

    expect($data['copy']['title'])->toBe(__('company.steps.main.label'))
        ->and($data['copy']['save'])->toBe(__('company.save'))
        ->and($fields['legal_name']['label'])->toBe(__('company.fields.legal_name'))
        ->and($fields['legal_name']['value'])->toBe('Yuca Qzv SA')
        ->and($fields['country_id']['value'])->toBe($country->id)
        ->and($fields['province_id']['value'])->toBe($province->id)
        ->and($fields['region_id']['value'])->toBe($region->id)
        ->and(collect($fields['region_id']['options'])->pluck('label')->all())->toBe(['Spinetti Qzv'])
        ->and(collect($fields['tax_condition_id']['options'])->pluck('value')->all())->toBe([$tax->id])
        ->and($fields->keys()->filter(fn (string $key): bool => str_contains($key, 'logo'))->all())->toBe([]);
});

test('saving the main step changes only what was sent, and a body can never name a stored logo path', function (): void {
    ['company' => $company] = companyEditPlace();

    $response = companyEditCall(companyEditToken(), 'PUT', '/main', ['address' => 'Av. Nueva Qzv 742', 'logo_path_light' => '../../.env'])->assertOk();

    // The web's toast for a saved change, never its "nothing changed".
    expect($response->json('message'))->not->toBe(__('notifications.no_changes'))
        ->and($company->refresh()->address)->toBe('Av. Nueva Qzv 742')
        ->and($company->legal_name)->toBe('Yuca Qzv SA')
        ->and($company->logo_path_light)->toBe('logos/light.svg')
        ->and(collect($response->json('data.fields'))->firstWhere('key', 'address')['value'])->toBe('Av. Nueva Qzv 742');
});

test('text is kept as the web keeps it: runs of spaces and line breaks fold to one space, payment lines stay', function (): void {
    ['company' => $company] = companyEditPlace();
    $token = companyEditToken();

    companyEditCall($token, 'PUT', '/main', ['legal_name' => "Yuca\n\n  Qzv   SA", 'address' => "Calle 24\tQzv"])->assertOk();
    companyEditCall($token, 'PUT', '/commercial', ['payment_instructions' => "Banco Qzv\nCuenta 0102"])->assertOk();

    expect($company->refresh()->legal_name)->toBe('Yuca Qzv SA')
        ->and($company->address)->toBe('Calle 24 Qzv')
        ->and($company->payment_instructions)->toBe("Banco Qzv\nCuenta 0102");
});

test('the main step refuses what the web refuses, under the key of its field', function (array $body, string $key): void {
    companyEditPlace();

    companyEditCall(companyEditToken(), 'PUT', '/main', $body)->assertStatus(422)->assertJsonValidationErrors($key);
})->with([
    'an empty address' => [['address' => ''], 'address'],
    'a region that does not exist' => [['region_id' => 999999], 'region_id'],
    'a tax id too long' => [['tax_id' => str_repeat('9', 21)], 'tax_id'],
    'a list for a name' => [['legal_name' => ['x']], 'legal_name'],
]);

test('the lists follow a choice: provinces and tax conditions of the country, regions of the province', function (): void {
    ['country' => $country, 'province' => $province] = companyEditPlace();
    Province::factory()->create(['name' => 'Otra Qzy', 'is_active' => true]);

    $data = companyEditCall(companyEditToken(), 'GET', '/places?country='.$country->id.'&province='.$province->id)->assertOk()->json('data');

    expect(collect($data['provinces'])->pluck('label')->all())->toBe(['Mérida Qzv'])
        ->and(collect($data['regions'])->pluck('label')->all())->toBe(['Spinetti Qzv'])
        ->and($data['taxConditions'])->toHaveCount(1);
});

test('the commercial step saves the week, the networks in order without the blank row, and keeps what was not sent', function (): void {
    ['company' => $company] = companyEditPlace();
    $instagram = SocialNetwork::factory()->create(['is_active' => true]);
    $x = SocialNetwork::factory()->create(['is_active' => true]);
    $token = companyEditToken();

    $week = [1 => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'], 0 => ['open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']];
    companyEditCall($token, 'PUT', '/commercial', [
        'week' => $week,
        'social' => [
            ['social_network_id' => $x->id, 'url' => 'https://x.com/qzv'],
            ['social_network_id' => null, 'url' => ''],
            ['social_network_id' => $instagram->id, 'url' => 'https://instagram.com/qzv'],
        ],
    ])->assertOk();

    expect($company->refresh()->opening_schedule)->toBe([1 => ['09:00', '18:00']])
        ->and($company->email)->toBe('soporte@qzv.test')
        ->and($company->socialLinks()->orderBy('sort_order')->pluck('social_network_id')->all())->toBe([$x->id, $instagram->id]);

    companyEditCall($token, 'PUT', '/commercial', ['phone' => '+58 424 8131833'])->assertOk();

    expect($company->socialLinks()->count())->toBe(2);
});

test('the commercial step refuses a bad hour under its day, and never creates the company', function (): void {
    companyEditPlace();
    $token = companyEditToken();

    companyEditCall($token, 'PUT', '/commercial', ['week' => [1 => ['open' => true, 'opens_at' => '9', 'closes_at' => '18:00']]])
        ->assertStatus(422)->assertJsonValidationErrors('opening_schedule.1.0');

    // An hour that is not text is refused, never cast into a word.
    companyEditCall($token, 'PUT', '/commercial', ['week' => [1 => ['open' => true, 'opens_at' => ['x'], 'closes_at' => '18:00']]])
        ->assertStatus(422)->assertJsonValidationErrors('week.1.opens_at');

    Company::query()->delete();

    companyEditCall($token, 'PUT', '/commercial', ['email' => 'hola@qzv.test'])->assertNotFound();
});

test('the first address the company gets sends the welcome mail through the channel, once', function (): void {
    ['company' => $company] = companyEditPlace();
    $company->forceFill(['email' => null])->save();
    $token = companyEditToken();

    companyEditCall($token, 'PUT', '/commercial', ['email' => 'hola@qzv.test'])->assertOk();
    companyEditCall($token, 'PUT', '/commercial', ['email' => 'otra@qzv.test'])->assertOk();

    Mail::assertQueued(NewCompany::class, 1);
});

test('a saved network is removed at once, and only one of this company', function (): void {
    ['company' => $company] = companyEditPlace();
    $link = SocialLink::factory()->for($company, 'linkable')->create();
    $businessLink = SocialLink::factory()->for(Business::factory()->create(), 'linkable')->create();
    $token = companyEditToken();

    companyEditCall($token, 'DELETE', '/social/'.$link->id)->assertOk();
    companyEditCall($token, 'DELETE', '/social/'.$businessLink->id)->assertNotFound();

    expect($company->socialLinks()->count())->toBe(0)
        ->and(SocialLink::query()->whereKey($businessLink->id)->exists())->toBeTrue();
});

test('the sheet carries the landing footer as the saved row makes it, and it follows a save', function (): void {
    companyEditPlace();
    $token = companyEditToken();

    $preview = companyEditCall($token, 'GET', '/commercial')->assertOk()->json('data.preview');

    expect($preview['title'])->toBe(__('company.preview.title'))
        ->and(collect($preview['lines'])->pluck('text'))->toContain('soporte@qzv.test')
        ->and($preview['copyright'])->toContain('Yuca Qzv SA');

    $after = companyEditCall($token, 'PUT', '/commercial', ['email' => 'hola@qzv.test'])->assertOk()->json('data.preview');

    expect(collect($after['lines'])->pluck('text'))->toContain('hola@qzv.test')->not->toContain('soporte@qzv.test');
});

test('with no company yet, only the first step is offered, as the web locks the second', function (): void {
    $token = companyEditToken();

    expect(companyEditCall($token, 'GET', '')->assertOk()->json('data.edit.steps'))->not->toHaveKey('contact');

    companyEditPlace();

    expect(companyEditCall($token, 'GET', '')->assertOk()->json('data.edit.steps.contact'))->toBe('commercial');
});

test('only who holds the company key edits it: support cannot even read the form', function (): void {
    companyEditPlace();
    $token = companyEditToken('support');

    companyEditCall($token, 'GET', '/main')->assertForbidden();
    companyEditCall($token, 'PUT', '/main', ['address' => 'Nada Qzv'])->assertForbidden();
    companyEditCall($token, 'DELETE', '/social/1')->assertForbidden();
});
