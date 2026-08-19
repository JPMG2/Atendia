<?php

declare(strict_types=1);

use App\Models\SocialNetwork;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');
});

test('clicking a table row opens the edit form with the record already loaded', function (): void {
    $network = SocialNetwork::factory()->create([
        'name' => 'Instagram',
        'url' => 'https://www.instagram.com/',
        'icon' => 'instagram',
        'abbreviation' => 'IG',
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');

    $page->click('Redes sociales')
        ->assertSee($network->name);

    // The row click must swap the view AND load the record server-side.
    $page->click($network->name)
        ->assertSee('Editando')
        ->assertValue('name', $network->name)
        ->assertValue('url', $network->url)
        ->assertValue('abbreviation', $network->abbreviation)
        ->assertNoJavaScriptErrors();

    // The combobox shows the LABEL; the value travels in its hidden field.
    expect($page->script('document.querySelector("#if-icon").value'))->toBe('instagram');
});

test('saving an edit takes the user back to the list showing the new value', function (): void {
    $network = SocialNetwork::factory()->create([
        'name' => 'Twitter', 'url' => 'https://twitter.com/', 'icon' => 'x-twitter', 'abbreviation' => 'TW',
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Redes sociales')->click($network->name);

    $page->fill('name', 'X (Twitter)');
    $page->click('Guardar cambios');

    // assertSee retries until the round trip lands and Alpine repaints the table;
    // the display check below does not retry, so it has to come after.
    $page->assertSee('X (Twitter)')
        ->assertNoJavaScriptErrors();

    expect(visibleCatalogView($page))->toBe('list');

    expect($network->fresh()->name)->toBe('X (Twitter)');
});

test('picking an icon from the combobox really persists it', function (): void {
    // The icon is a KEY of config/icons.php, not free text: the combobox is the
    // only way to pick one that <x-icon> can actually draw.
    $network = SocialNetwork::factory()->create([
        'name' => 'Instagram', 'url' => 'https://www.instagram.com/', 'icon' => null,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Redes sociales')->click($network->name);

    // Type to filter, then pick the option: the whole point of the combobox.
    $page->fill('#if-icon', 'instagram');
    $page->click('instagram');
    $page->click('Guardar cambios');

    $page->assertSee('instagram')
        ->assertNoJavaScriptErrors();

    expect($network->fresh()->icon)->toBe('instagram');
});

test('starting a new network after opening one for edit shows empty inputs', function (): void {
    $network = SocialNetwork::factory()->create([
        'name' => 'Instagram', 'url' => 'https://www.instagram.com/', 'abbreviation' => 'IG',
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Redes sociales')->click($network->name);

    // Leave without saving, then start a new one: nothing of the old record may survive.
    $page->click('Volver')->click('Crear red social');

    $page->assertValue('name', '')
        ->assertValue('url', '')
        ->assertValue('abbreviation', '')
        ->assertSee('Nueva red social')
        ->assertNoJavaScriptErrors();
});

test('the front-end validation stops the request before it reaches the server', function (): void {
    // The wording is the discriminator: this exact sentence comes from form-guard.js.
    $network = SocialNetwork::factory()->create([
        'name' => 'Instagram', 'url' => 'https://www.instagram.com/',
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/catalogs');
    $page->click('Redes sociales')->click($network->name);

    $page->fill('name', 'AB');
    $page->click('Guardar cambios');

    $page->assertVisible('#if-name-err');

    expect($page->text('#if-name-err'))->toBe('Debe tener al menos 3 caracteres.');
    expect($network->fresh()->name)->toBe('Instagram');

    $page->assertNoJavaScriptErrors();
});
