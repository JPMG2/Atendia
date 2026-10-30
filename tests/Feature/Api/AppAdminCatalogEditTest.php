<?php

declare(strict_types=1);

use App\Classes\Main\CatalogEditor;
use App\Mail\DeviceChallengeCode;
use App\Models\Country;
use App\Models\Currency;
use App\Models\SocialNetwork;
use App\Models\User;
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

    preg_match_all('/<x-inputsform\.[a-z-]+\b[^>]*?\bname="([a-z_0-9]+)"/s', $form, $names);
    preg_match_all('/<x-inputsform\.[a-z-]+\b[^>]*?:label="__\(\'([^\']+)\'\)"/s', $form, $labels);

    $fields = CatalogEditor::FORMS[$slug]::phoneFields();

    preg_match('/title-key="([a-z_]+)"/', $form, $title);

    expect(array_column($fields, 'key'))->toBe($names[1])
        ->and(array_column($fields, 'label'))->toBe(array_map(fn (string $key): string => __($key), $labels[1]))
        ->and(CatalogEditor::TITLE_KEYS[$slug] ?? 'name')->toBe($title[1] ?? 'name');
})->with(array_keys(CatalogEditor::FORMS));
