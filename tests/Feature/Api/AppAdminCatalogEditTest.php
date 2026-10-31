<?php

declare(strict_types=1);

use App\Classes\Main\CatalogEditor;
use App\Mail\DeviceChallengeCode;
use App\Mail\HolidayBridgeNotice;
use App\Models\Business;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\Currency;
use App\Models\SeasonalWindow;
use App\Models\SocialNetwork;
use App\Models\User;
use App\Models\WhatsappVariant;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Catalog rows edited from the app
|--------------------------------------------------------------------------
| The phone creates, edits and removes through the web's own pieces: the
| master's Form says its fields, rules and Actions once (`EditableFromPhone`),
| `CatalogEditor` and `CatalogRemoval` serve them.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
});

function catalogEditToken(string $role = 'admin'): string
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

function catalogEditCall(string $token, string $method, string $path, array $body = []): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->json($method, '/api/v1/admin/catalogs/'.$path, $body);
}

test('the list of an editable master says so and names its create button; a read-only one does not', function (): void {
    $token = catalogEditToken();

    catalogEditCall($token, 'GET', 'currency')->assertOk()->assertJsonPath('data.editable', true)->assertJsonPath('data.create', 'Crear moneda');
    catalogEditCall($token, 'GET', 'plan')->assertOk()->assertJsonPath('data.editable', false)->assertJsonPath('data.create', null);
});

test('the form comes with the web form\'s fields, words and today\'s values', function (): void {
    $token = catalogEditToken();
    $euro = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true]);

    $sheet = catalogEditCall($token, 'GET', 'currency/form/'.$euro->id)->assertOk()->json('data');

    expect($sheet['title'])->toBe('EUR')
        ->and($sheet['eyebrow'])->toBe(__('catalog.common.editing'))
        ->and(array_column($sheet['fields'], 'key'))->toBe(['code', 'name', 'symbol', 'decimal_places', 'is_active'])
        ->and(array_column($sheet['fields'], 'value'))->toBe(['EUR', 'Euro', '€', 2, true])
        ->and($sheet['fields'][0]['hint'])->toBe(__('catalog.currency.fields.code_hint'))
        ->and($sheet['copy']['save'])->toBe(__('catalog.common.save'))
        ->and($sheet['deletable'])->toBeTrue();

    $new = catalogEditCall($token, 'GET', 'currency/form')->assertOk()->json('data');

    expect($new['title'])->toBe(__('catalog.currency.new_title'))
        ->and($new['copy']['save'])->toBe(__('catalog.currency.create'))
        ->and($new['deletable'])->toBeFalse();

    catalogEditCall($token, 'GET', 'currency/form/999999')->assertNotFound();
});

test('creating and editing go through the web\'s DTO, rules and Actions', function (): void {
    $token = catalogEditToken();

    $id = catalogEditCall($token, 'POST', 'currency', ['code' => 'usd', 'name' => '  Dólar   Estadounidense ', 'symbol' => 'US$', 'decimal_places' => 2, 'is_active' => true])
        ->assertCreated()->json('data.id');

    $saved = Currency::query()->findOrFail($id);
    expect($saved->code)->toBe('USD')->and($saved->name)->toBe('Dólar Estadounidense');

    // A partial body: what it does not name stays.
    catalogEditCall($token, 'PUT', 'currency/'.$id, ['symbol' => '$'])->assertOk();
    expect($saved->fresh()->symbol)->toBe('$')->and($saved->fresh()->name)->toBe('Dólar Estadounidense');

    // The web's refusal, keyed by its field and in its words.
    catalogEditCall($token, 'POST', 'currency', ['code' => 'USD', 'name' => 'Otro', 'symbol' => '$'])
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', __('validation.unique', ['attribute' => config('nicename.code')]));
});

test('a value of the wrong kind is a field error, never a crash, and a valid one is cast', function (array $body, string $field): void {
    $token = catalogEditToken();

    catalogEditCall($token, 'POST', 'currency', ['code' => 'AAB', 'name' => 'Prueba', 'symbol' => '$', ...$body])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'a decimal in Decimales' => [['decimal_places' => 1.5], 'decimal_places'],
    'a word in Decimales' => [['decimal_places' => 'abc'], 'decimal_places'],
    'a word in a switch' => [['is_active' => 'quizás'], 'is_active'],
    'a null switch' => [['is_active' => null], 'is_active'],
    'a number as the name' => [['name' => 12345], 'name'],
    'a list as the name' => [['name' => ['x']], 'name'],
]);

test('numbers and switches sent as text are read as the web reads them', function (): void {
    $token = catalogEditToken();

    $id = catalogEditCall($token, 'POST', 'currency', ['code' => 'AAC', 'name' => 'Prueba', 'symbol' => '$', 'decimal_places' => '0', 'is_active' => '0'])
        ->assertCreated()->json('data.id');

    expect(Currency::query()->findOrFail($id))->decimal_places->toBe(0)->is_active->toBeFalse();
});

test('the header names the row by the field the web form\'s header uses', function (): void {
    $token = catalogEditToken();
    $network = SocialNetwork::factory()->create(['name' => 'Mastodon', 'abbreviation' => null]);

    expect(catalogEditCall($token, 'GET', 'social-network/form/'.$network->id)->assertOk()->json('data.title'))->toBe('Mastodon');
});

test('removing asks first, refuses a row in use and offers to switch it off', function (): void {
    $token = catalogEditToken();
    $euro = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'is_active' => true]);
    $peso = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino', 'is_active' => true]);
    Country::factory()->create(['currency_id' => $peso->id]);

    catalogEditCall($token, 'GET', "currency/{$euro->id}/removal")->assertOk()
        ->assertJsonPath('data.status', 'removed')
        ->assertJsonPath('data.message', __('catalog.remove.confirm', ['name' => 'Euro']));

    catalogEditCall($token, 'GET', "currency/{$peso->id}/removal")->assertOk()
        ->assertJsonPath('data.status', 'in_use')
        ->assertJsonPath('data.deactivate', __('catalog.remove.deactivate'));

    catalogEditCall($token, 'DELETE', "currency/{$peso->id}")->assertStatus(409);
    expect(Currency::query()->whereKey($peso->id)->exists())->toBeTrue();

    catalogEditCall($token, 'POST', "currency/{$peso->id}/deactivate")->assertOk();
    expect($peso->fresh()->is_active)->toBeFalse();

    catalogEditCall($token, 'DELETE', "currency/{$euro->id}")->assertOk();
    expect(Currency::query()->whereKey($euro->id)->exists())->toBeFalse();
});

test('a master the phone does not edit yet answers like an unknown one', function (): void {
    $token = catalogEditToken();

    catalogEditCall($token, 'GET', 'plan/form')->assertNotFound();
    catalogEditCall($token, 'POST', 'plan', ['name' => 'x'])->assertNotFound();
});

test('a person without the catalogs key cannot open, create or remove', function (): void {
    $token = catalogEditToken('support');
    $euro = Currency::factory()->create();

    catalogEditCall($token, 'GET', 'currency/form')->assertForbidden();
    catalogEditCall($token, 'POST', 'currency', ['code' => 'XYZ'])->assertForbidden();
    catalogEditCall($token, 'DELETE', "currency/{$euro->id}")->assertForbidden();
});

test('every master the phone edits opens a row and saves it back untouched, through its own rules', function (string $slug): void {
    $token = catalogEditToken();
    $model = CatalogEditor::FORMS[$slug]::wiring()->model;
    $row = $model::factory()->create();

    $sheet = catalogEditCall($token, 'GET', "{$slug}/form/{$row->getKey()}")->assertOk()->json('data');
    $body = collect($sheet['fields'])->mapWithKeys(fn (array $field): array => [$field['key'] => $field['value']])->all();

    expect($sheet['title'])->not->toBe('');
    catalogEditCall($token, 'PUT', "{$slug}/{$row->getKey()}", $body)->assertOk();
})->with(array_keys(CatalogEditor::FORMS));

test('every master the phone edits declares exactly the fields and labels its web form draws', function (string $slug): void {
    $blade = File::get(resource_path("views/components/catalog/⚡{$slug}.blade.php"));
    $form = substr($blade, (int) strpos($blade, '<x-slot:form>'));

    // An attribute may hold `->` (`:value="$form->data->…"`): it does not close the tag.
    preg_match_all('/<x-inputsform\.[a-z-]+\b(?:->|[^>])*?\bname="([a-z_0-9]+)"/s', $form, $names);
    preg_match_all('/<x-inputsform\.[a-z-]+\b(?:->|[^>])*?:label="__\(\'([^\']+)\'\)"/s', $form, $labels);

    $fields = CatalogEditor::FORMS[$slug]::phoneFields();

    preg_match('/title-key="([a-z_]+)"/', $form, $title);

    // The asterisk too: a field the web marks required, and only those.
    preg_match_all('/<x-inputsform\.[a-z-]+\b((?:->|[^>])*?)\/?>/s', $form, $tags);
    $required = collect($tags[1])->filter(fn (string $tag): bool => (bool) preg_match('/\srequired(\s|$)/', $tag))
        ->map(fn (string $tag): string => preg_match('/\bname="([a-z_0-9]+)"/', $tag, $name) ? $name[1] : '')->values()->all();

    expect(array_values(array_unique(array_column(array_filter($fields, fn (array $field): bool => $field['required'] ?? false), 'key'))))->toBe(array_values(array_unique($required)))
        ->and(array_column($fields, 'key'))->toBe($names[1])
        ->and(array_column($fields, 'label'))->toBe(array_map(fn (string $key): string => __($key), $labels[1]))
        ->and(CatalogEditor::TITLE_KEYS[$slug] ?? 'name')->toBe($title[1] ?? 'name');
})->with(array_keys(CatalogEditor::FORMS));

test('a season is saved from its two ends, and one that ends before it starts is refused on its end', function (): void {
    $token = catalogEditToken();

    $id = catalogEditCall($token, 'POST', 'seasonal-window', ['name' => 'Navidad 2026', 'range' => '2026-12-20..2026-12-26', 'priority' => 1, 'is_active' => true])
        ->assertCreated()->json('data.id');

    $season = SeasonalWindow::query()->findOrFail($id);
    expect($season->starts_at->toDateString())->toBe('2026-12-20')->and($season->ends_at->toDateString())->toBe('2026-12-26');

    // A range closed on one date is a season of one day, as on the web.
    catalogEditCall($token, 'PUT', "seasonal-window/{$id}", ['range' => '2026-12-24'])->assertOk();
    expect($season->fresh()->ends_at->toDateString())->toBe('2026-12-24');

    catalogEditCall($token, 'PUT', "seasonal-window/{$id}", ['range' => '2026-12-26..2026-12-20'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ends_at');

    // "Desde" erased on the phone: refused under it, never saved as a one-day season on "Hasta".
    catalogEditCall($token, 'PUT', "seasonal-window/{$id}", ['range' => '..2026-12-31'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');

    // A date typed half or impossible is said the way the phone types it.
    catalogEditCall($token, 'PUT', "seasonal-window/{$id}", ['range' => '24/12/202..2026-12-31'])
        ->assertStatus(422)
        ->assertJsonPath('errors.starts_at.0', __('validation.custom.starts_at.date_format'));
    expect($season->fresh()->starts_at->toDateString())->toBe('2026-12-24');
});

test('the phone cannot save what the web\'s browser would have refused', function (array $body, string $field): void {
    $token = catalogEditToken();
    $country = Country::factory()->create();

    catalogEditCall($token, 'POST', 'country-holiday', ['country_id' => $country->id, 'name' => 'Feriado', 'kind' => 'fixed', 'month' => 5, 'day' => 1, ...$body])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(CountryHoliday::query()->count())->toBe(0);
})->with([
    'markup in the name' => [['name' => '<b>Carnaval</b>'], 'name'],
    'a shape that does not exist' => [['kind' => 'xyz'], 'kind'],
    'a date that does not exist' => [['kind' => 'once', 'on_date' => '2026-02-31'], 'on_date'],
]);

test('a season\'s week with a day that is not one is refused, not dropped', function (array $week): void {
    $token = catalogEditToken();
    $season = SeasonalWindow::factory()->create();

    catalogEditCall($token, 'POST', 'whatsapp-variant', ['seasonal_window_id' => $season->id, 'override_schedule' => true, 'opening_schedule' => $week])
        ->assertStatus(422);

    expect(WhatsappVariant::query()->count())->toBe(0);
})->with([
    'a ninth day' => [['9' => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '14:00']]],
    'a day that is a word' => [['1' => 'x']],
]);

test('a season\'s own week is kept only while it is switched on, and a half-typed hour names its box', function (): void {
    $token = catalogEditToken();
    $season = SeasonalWindow::factory()->create();
    $week = ['1' => ['open' => true, 'opens_at' => '09:00', 'closes_at' => '14:00'], '6' => ['open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']];

    // A new variant's week is the web's grid: seven closed days that open at 09:00–18:00.
    $blank = collect(catalogEditCall($token, 'GET', 'whatsapp-variant/form')->assertOk()->json('data.fields'))->firstWhere('key', 'opening_schedule')['value'];
    expect($blank)->toHaveCount(7)->and($blank[1])->toBe(['open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']);

    $id = catalogEditCall($token, 'POST', 'whatsapp-variant', ['seasonal_window_id' => $season->id, 'override_schedule' => true, 'opening_schedule' => $week, 'is_active' => true])
        ->assertCreated()->json('data.id');

    expect(WhatsappVariant::query()->findOrFail($id)->opening_schedule)->toBe([1 => ['09:00', '14:00']]);

    catalogEditCall($token, 'PUT', "whatsapp-variant/{$id}", ['opening_schedule' => ['1' => ['open' => true, 'opens_at' => '9', 'closes_at' => '14:00']]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('opening_schedule.1.0');

    catalogEditCall($token, 'PUT', "whatsapp-variant/{$id}", ['override_schedule' => false])->assertOk();
    expect(WhatsappVariant::query()->findOrFail($id)->opening_schedule)->toBeNull();

    catalogEditCall($token, 'POST', 'whatsapp-variant', ['seasonal_window_id' => $season->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('seasonal_window_id');
});

test('a holiday demands the date of the shape it is and keeps none of the others', function (): void {
    $token = catalogEditToken();
    $country = Country::factory()->create();

    catalogEditCall($token, 'POST', 'country-holiday', ['country_id' => $country->id, 'name' => 'Día de la Madre', 'kind' => 'nth', 'month' => 5, 'day' => 10, 'is_active' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nth', 'weekday']);

    $id = catalogEditCall($token, 'POST', 'country-holiday', ['country_id' => $country->id, 'name' => 'Día de la Madre', 'kind' => 'nth', 'month' => 5, 'day' => 10, 'nth' => 2, 'weekday' => 0, 'is_active' => true])
        ->assertCreated()->json('data.id');

    $holiday = CountryHoliday::query()->findOrFail($id);
    expect($holiday->kind())->toBe('nth')->and($holiday->day)->toBeNull();

    catalogEditCall($token, 'PUT', "country-holiday/{$id}", ['kind' => 'fixed', 'month' => 4, 'day' => 31])
        ->assertStatus(422)
        ->assertJsonPath('errors.day.0', __('catalog.country_holiday.errors.no_such_day'));

    catalogEditCall($token, 'PUT', "country-holiday/{$id}", ['kind' => 'once', 'on_date' => '2026-11-02'])->assertOk();
    expect($holiday->fresh())->kind()->toBe('once')->month->toBeNull()->weekday->toBeNull();
});

test('a one-year day saved from the phone brings the web\'s question, and the mail leaves only when accepted', function (): void {
    Mail::fake();
    $token = catalogEditToken();
    $country = Country::factory()->create();
    $business = Business::factory()->create(['country_id' => $country->id, 'is_demo' => false]);
    User::factory()->create(['business_id' => $business->id, 'email' => 'duena@kiosco.test']);

    $saved = catalogEditCall($token, 'POST', 'country-holiday', ['country_id' => $country->id, 'name' => 'Feriado por decreto', 'kind' => 'once', 'on_date' => '2026-11-23', 'is_active' => true])
        ->assertCreated()
        ->assertJsonPath('data.offer.marked', ['2026-11-23'])
        ->assertJsonPath('data.offer.dismiss', __('catalog.country_holiday.bridge.notify_skip'))
        ->json('data');

    Mail::assertNotQueued(HolidayBridgeNotice::class);

    // Saved again without moving it: not news twice.
    catalogEditCall($token, 'PUT', "country-holiday/{$saved['id']}", ['name' => 'Feriado nacional por decreto'])->assertOk()->assertJsonPath('data.offer', null);

    catalogEditCall($token, 'POST', "country-holiday/{$saved['id']}/notice")->assertOk()->assertJsonPath('message', 'Listo. Se avisó a 1 negocio.');
    Mail::assertQueued(HolidayBridgeNotice::class, fn (HolidayBridgeNotice $mail): bool => $mail->dates === ['2026-11-23'] && ! $mail->bridge);

    // A holiday that repeats asks nothing, and no other master has a notice.
    $yearly = CountryHoliday::factory()->create(['country_id' => $country->id]);
    catalogEditCall($token, 'POST', "country-holiday/{$yearly->id}/notice")->assertNotFound();
    catalogEditCall($token, 'POST', 'currency/1/notice')->assertNotFound();
});

test('the notice tells each business once, never a suspended one, never about a day gone, and follows the live row of a date', function (): void {
    Mail::fake();
    $token = catalogEditToken();
    $country = Country::factory()->create();
    $open = Business::factory()->create(['country_id' => $country->id, 'is_demo' => false]);
    User::factory()->create(['business_id' => $open->id, 'email' => 'abierto@kiosco.test']);
    $suspended = Business::factory()->create(['country_id' => $country->id, 'is_demo' => false, 'suspended_at' => now()]);
    User::factory()->create(['business_id' => $suspended->id, 'email' => 'suspendido@kiosco.test']);

    // A bridge marked and switched off on that date first: the decree typed in later is the live one.
    CountryHoliday::factory()->create(['country_id' => $country->id, 'name' => __('catalog.country_holiday.bridge.name'), 'month' => null, 'day' => null, 'on_date' => '2026-12-24', 'is_active' => false]);

    $id = catalogEditCall($token, 'POST', 'country-holiday', ['country_id' => $country->id, 'name' => 'Feriado por decreto', 'kind' => 'once', 'on_date' => '2026-12-24', 'is_active' => true])
        ->assertCreated()
        ->assertJsonPath('data.offer.title', '¿Avisar a 1 negocio de '.$country->name.'?')
        ->json('data.id');

    catalogEditCall($token, 'POST', "country-holiday/{$id}/notice")->assertOk()->assertJsonPath('message', 'Listo. Se avisó a 1 negocio.');
    catalogEditCall($token, 'POST', "country-holiday/{$id}/notice")->assertOk()->assertJsonPath('message', __('catalog.country_holiday.bridge.repeated'));

    Mail::assertQueued(HolidayBridgeNotice::class, 1);
    Mail::assertQueued(HolidayBridgeNotice::class, fn (HolidayBridgeNotice $mail): bool => $mail->hasTo('abierto@kiosco.test') && ! $mail->bridge);

    // Moved to another country on the same date: news for that country.
    $other = Country::factory()->create();
    $there = Business::factory()->create(['country_id' => $other->id, 'is_demo' => false]);
    User::factory()->create(['business_id' => $there->id, 'email' => 'otro@kiosco.test']);
    catalogEditCall($token, 'PUT', "country-holiday/{$id}", ['country_id' => $other->id])->assertOk()->assertJsonPath('data.offer.marked', ['2026-12-24']);

    // A day already gone is no news.
    catalogEditCall($token, 'PUT', "country-holiday/{$id}", ['on_date' => '2026-03-01'])->assertOk()->assertJsonPath('data.offer', null);
    catalogEditCall($token, 'POST', "country-holiday/{$id}/notice")->assertNotFound();
});

test('a holiday is never removed from the phone, as the web offers no delete for it', function (): void {
    $token = catalogEditToken();
    $holiday = CountryHoliday::factory()->create();

    expect(catalogEditCall($token, 'GET', "country-holiday/form/{$holiday->id}")->assertOk()->json('data.deletable'))->toBeFalse();

    catalogEditCall($token, 'GET', "country-holiday/{$holiday->id}/removal")->assertNotFound();
    catalogEditCall($token, 'DELETE', "country-holiday/{$holiday->id}")->assertNotFound();
    catalogEditCall($token, 'POST', "country-holiday/{$holiday->id}/deactivate")->assertNotFound();
    expect(CountryHoliday::query()->whereKey($holiday->id)->exists())->toBeTrue();
});
