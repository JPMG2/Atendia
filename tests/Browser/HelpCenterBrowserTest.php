<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Help, in a real browser
|--------------------------------------------------------------------------
| The handover from an article that did not solve it into the report form is
| an Alpine event crossing two components: only a browser proves it lands.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(HelpArticleSeeder::class);

    $business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($business)->save();
    $user->syncRoles(['client']);
    $this->actingAs($user->refresh());
});

test('the help screen opens from the menu with its answers and the report in reach', function (): void {
    visit('/ayuda')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Cómo conecto mi WhatsApp')
        ->assertPresent('[data-testid="help-report"]')
        ->click('[data-testid="help-article-conectar-whatsapp"]')
        ->waitForText('Dispositivos vinculados')
        ->assertSee(__('help.useful'))
        ->screenshot(filename: 'help-open-desktop');
});

test('a thumb down hands her to the report with the article already named', function (): void {
    visit('/ayuda')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="help-article-conectar-whatsapp"]')
        ->waitForText(__('help.useful'))
        ->click('[data-testid="help-no-conectar-whatsapp"]')
        ->waitForText(__('support.title'))
        ->assertSee('Cómo conecto mi WhatsApp')
        ->screenshot(filename: 'help-handover-desktop');
});

test('the report suggests answers while she writes, without hiding the send button', function (): void {
    visit('/whatsapp')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="support-open"]')
        ->waitForText(__('support.fields.body'))
        ->type('[name="body"]', 'mi asistente dejo de responder desde ayer')
        // The field is debounced at 500ms and then Livewire answers.
        ->wait(2)
        ->assertPresent('[data-testid="support-suggestions"]')
        ->assertSee(__('support.maybe_this'))
        ->assertSee(__('support.send'))
        ->screenshot(filename: 'help-deflection-desktop');
});

test('the help reads on a phone and in the dark', function (): void {
    visit('/ayuda')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid="help-article-conectar-whatsapp"]')
        ->waitForText('Dispositivos vinculados')
        ->screenshot(filename: 'help-open-phone');

    visit('/ayuda')->resize(1280, 900)
        ->click('@theme-toggle')
        ->click('[data-testid="help-article-importar-productos-excel"]')
        ->waitForText('Arrastra el archivo')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'help-open-dark');
});
