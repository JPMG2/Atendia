<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use App\Models\SocialLink;
use App\Models\TaxCondition;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SocialNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(SocialNetworkSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin);
});

test('the company screen opens on the main step with no JS errors', function (): void {
    $page = visit('/admin/company');

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Razón social')
        ->assertSee('Identificación fiscal')
        // The second step is on screen, but its panel stays hidden until it opens.
        ->assertSee('Datos comerciales')
        ->assertDontSee('Email de soporte');
});

test('with no company saved the second step does not open, and says what opens it', function (): void {
    // This is the whole point of the stepper: the commercial data hangs off a
    // record that does not exist yet, so clicking the step leads nowhere and the
    // screen tells the user what to do instead of failing silently.
    $page = visit('/admin/company');

    // The locale is the neutral `es` one (tuteo); es_AR only overrides the voseo.
    $page->assertSee('Guarda la configuración principal para abrir este paso.')
        ->assertNoJavaScriptErrors()
        ->assertDontSee('Email de soporte');

    // The browser itself refuses the step: it is not just painted as unavailable.
    expect($page->script('document.querySelectorAll(\'.stepper-step[aria-disabled="true"]\').length'))->toBe(1);

    // And the lock lives in the handler, not in the styling: even a click fired
    // straight from JS —which skips every actionability check— leaves the panel shut.
    $page->script('document.querySelectorAll(".stepper-step")[1].click()');

    $page->assertDontSee('Email de soporte')
        ->assertSee('Razón social');
});

test('with the company already saved the second step swaps the panel', function (): void {
    // Nothing left to order once the record exists: both steps are open.
    Company::factory()->create();

    $page = visit('/admin/company');

    $page->click('Datos comerciales')
        ->assertSee('Email de soporte')
        ->assertSee('Redes sociales')
        // The main panel is the one that hides now.
        ->assertDontSee('Razón social');
});

test('deleting a network asks first, through the system dialog', function (): void {
    // Golden rule: no native confirm anywhere. What proves it is that the warning
    // is IN the page — a browser confirm would not be in the DOM at all.
    $company = Company::factory()->create();
    $link = SocialLink::factory()->for($company, 'linkable')->create();

    $page = visit('/admin/company');

    $page->click('Datos comerciales')
        ->click('@social-remove')
        ->wait(1);

    $page->assertNoJavaScriptErrors()
        ->assertSee('¿Eliminar esta red?')
        ->assertSee('Eliminar la red');

    // Backing out leaves the link alone.
    $page->click('Cancelar')->wait(1);
    $this->assertDatabaseHas('social_links', ['id' => $link->id]);

    // Confirming deletes it right away, with no save in between.
    $page->click('@social-remove')->wait(1);
    $page->click('Eliminar la red')->wait(2);

    $page->assertNoJavaScriptErrors();
    $this->assertDatabaseMissing('social_links', ['id' => $link->id]);
});

test('a social network is picked, saved and still there after a reload', function (): void {
    // The rows are dynamic and now live on the server: what matters is that what
    // the user typed into a row it created survives the save.
    // Instagram already comes from SocialNetworkSeeder (see beforeEach): creating
    // it again would hit the unique name of the catalog.
    $company = Company::factory()->create();

    $page = visit('/admin/company');

    $page->click('Datos comerciales')
        ->fill('#if-email', 'hola@atendia.app');

    $page->fill('#if-social-0-network', 'instagram')->click('Instagram')->wait(1);
    $page->fill('#if-social-0-url', 'https://instagram.com/atendia');

    $page->click('Guardar cambios')->wait(2);

    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('companies', ['id' => $company->id, 'email' => 'hola@atendia.app']);
    $this->assertDatabaseHas('social_links', [
        'linkable_id' => $company->id,
        'url' => 'https://instagram.com/atendia',
    ]);

    // And it comes back from the database, not from what is left on screen.
    $page = visit('/admin/company');
    $page->click('Datos comerciales');

    expect($page->script('document.querySelector("#if-social-0-url").value'))
        ->toBe('https://instagram.com/atendia');
});

test('a social network row is added from the row itself', function (): void {
    // The list is not a fixed set: adding and removing live on the row, so the
    // whole thing stays on one line and no button floats underneath.
    Company::factory()->create();

    $page = visit('/admin/company');

    $page->click('Datos comerciales')
        // Only one row exists at first, so the selector is unambiguous.
        ->click('@social-add')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('changing the country empties the province and region combobox on screen too', function (): void {
    // The server nulls province_id/region_id, but what the user reads is Alpine's
    // own `query`, not the :value prop. If the label survived the reset, the field
    // would show a province that is no longer in its list and no longer in the DTO.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $chile = Country::factory()->create(['code' => 'CHL', 'name' => 'Chile']);

    $mendoza = Province::factory()->create(['country_id' => $argentina->id, 'name' => 'Mendoza']);
    Province::factory()->create(['country_id' => $chile->id, 'name' => 'Valparaiso']);

    Region::factory()->create(['province_id' => $mendoza->id, 'name' => 'Cuyo']);

    $page = visit('/admin/company');

    // Each pick is a `.live` round-trip that re-renders the combobox below it, so
    // the next field cannot be filled until that has landed.
    $page->fill('#if-country_id', 'argentina')->click('Argentina')->wait(1);
    $page->fill('#if-province_id', 'mendoza')->click('Mendoza')->wait(1);
    $page->fill('#if-region_id', 'cuyo')->click('Cuyo')->wait(1);

    // The three are filled in before the reset, so the assertion below means
    // something.
    expect($page->script('document.querySelector("#if-province_id").value'))->toBe('Mendoza');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('Cuyo');

    $page->fill('#if-country_id', 'chile')->click('Chile')->wait(1);

    expect($page->script('document.querySelector("#if-province_id").value'))->toBe('');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('');
});

test('discarding the main step puts the saved values back on screen', function (): void {
    // The real risk of a deferred wire:model: the server may already hold the
    // saved value, so restoring it changes nothing on its side and the typed
    // text could survive on screen. What matters is what the user reads.
    $mendoza = Province::factory()->create(['name' => 'Mendoza']);
    $cuyo = Region::factory()->create(['province_id' => $mendoza->id, 'name' => 'Cuyo']);

    Company::factory()->create(['legal_name' => 'AtendIa SRL', 'region_id' => $cuyo->id]);

    $page = visit('/admin/company');

    $page->fill('#if-legal_name', 'Escrito y arrepentido');
    $page->click('#if-region_id ~ .combo-clear');

    // Both are dirty ONLY in the browser: neither change has reached the server.
    expect($page->script('document.querySelector("#if-legal_name").value'))->toBe('Escrito y arrepentido');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('');

    $page->click('Descartar')->wait(1);

    $page->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#if-legal_name").value'))->toBe('AtendIa SRL');
    expect($page->script('document.querySelector("#if-region_id").value'))->toBe('Cuyo');
});

test('saving the main step opens the second one and takes the user there', function (): void {
    // The whole point of the two steps end to end: fill step one, save, and the
    // lock is gone and the user is standing on step two — without a page reload.
    $argentina = Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);
    $mendoza = Province::factory()->create(['country_id' => $argentina->id, 'name' => 'Mendoza']);
    Region::factory()->create(['province_id' => $mendoza->id, 'name' => 'Cuyo']);
    TaxCondition::factory()->create(['country_id' => $argentina->id, 'name' => 'Responsable inscripto']);

    $page = visit('/admin/company');

    $page->fill('#if-legal_name', 'AtendIa SRL');

    // Each pick is a `.live` round-trip that re-renders the combobox below it.
    $page->fill('#if-country_id', 'argentina')->click('Argentina')->wait(1);
    $page->fill('#if-province_id', 'mendoza')->click('Mendoza')->wait(1);
    $page->fill('#if-region_id', 'cuyo')->click('Cuyo')->wait(1);

    $page->fill('#if-address', 'Av. Siempre Viva 742');
    $page->fill('#if-tax_condition_id', 'responsable')->click('Responsable inscripto')->wait(1);
    $page->fill('#if-tax_id', '30123456789');

    $page->click('Guardar y continuar')->wait(2);

    $page->assertNoJavaScriptErrors()
        // Landed on step two...
        ->assertSee('Email de soporte')
        ->assertDontSee('Razón social')
        // ...and the lock is gone for good.
        ->assertDontSee('Guarda la configuración principal para abrir este paso.');

    $this->assertDatabaseHas('companies', ['legal_name' => 'AtendIa SRL']);
});

test('the clear button empties a picked combobox in one click', function (): void {
    // Before it, changing your mind meant selecting the whole label and deleting
    // it by hand before the search box was usable again.
    Country::factory()->create(['code' => 'ARG', 'name' => 'Argentina']);

    $page = visit('/admin/company');

    $page->fill('#if-country_id', 'argentina')->click('Argentina');

    expect($page->script('document.querySelector("#if-country_id").value'))->toBe('Argentina');

    // Scoped to this combobox: every field on the screen has a clear button.
    $page->click('#if-country_id ~ .combo-clear');

    expect($page->script('document.querySelector("#if-country_id").value'))->toBe('');

    // And the field is left ready to type, which is the whole point.
    expect($page->script('document.activeElement.id'))->toBe('if-country_id');
});

test('saving with the required fields empty shows the errors instead of costing a request', function (): void {
    // The front validation only exists if Alpine really resolved `companyForm`:
    // a registration that arrives late throws an expression error and the save
    // button quietly does nothing.
    $page = visit('/admin/company');

    // With no company loaded the button is the one that opens the next step.
    $page->click('Guardar y continuar')
        ->assertNoJavaScriptErrors()
        ->assertSee('Este campo es obligatorio.');
});
