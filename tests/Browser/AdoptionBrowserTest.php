<?php

declare(strict_types=1);

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LoginActivity;
use App\Models\User;
use Database\Seeders\AdoptionNudgeSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Adoption, in a real browser
|--------------------------------------------------------------------------
| The shape of the real platform on 2026-10-09: one account that never made
| its business, one stuck on the step after it for three weeks, one whose
| assistant answered, and a brand new one still inside its plazo.
*/

const ADOPTION_TABLE_VS_CARD_JS = 'Array.from(document.querySelectorAll(".pay-table")).filter(t => t.offsetParent !== null).map(t => {
    const card = t.closest(".card, [class*=card]").getBoundingClientRect();
    return Math.round(t.getBoundingClientRect().right - card.right);
}).reduce((a, b) => Math.max(a, b), -999)';

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(AdoptionNudgeSeeder::class);

    User::factory()->create(['name' => 'Lara Aguirre', 'email' => 'lara@aguirre.test', 'created_at' => now()->subDays(23)]);

    $stuck = User::factory()->create(['name' => 'Cliente de prueba', 'email' => 'prueba@conexion.test', 'created_at' => now()->subDays(40)]);
    $stuck->business()->associate(Business::factory()->create(['name' => 'Prueba Conexión', 'created_at' => now()->subDays(22)]))->save();
    LoginActivity::factory()->create(['user_id' => $stuck->id, 'created_at' => now()->subDays(22)]);

    $done = User::factory()->create(['name' => 'Sergio Andrés', 'email' => 'sergio@laboratoriovida.test', 'created_at' => now()->subDays(20)]);
    $done->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida', 'whatsapp_connected_at' => now()->subDays(10)]))->save();
    $conversation = Conversation::factory()->create(['business_id' => $done->business_id]);
    ConversationMessage::factory()->create([
        'business_id' => $done->business_id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Assistant,
    ]);
    LoginActivity::factory()->create(['user_id' => $done->id, 'created_at' => now()->subDays(3)]);

    User::factory()->create(['name' => 'Marta Nueva', 'email' => 'marta@nueva.test', 'created_at' => now()->subDays(2)]);

    $admin = User::factory()->create(['name' => 'Admin', 'email' => 'equipo@atendia.test']);
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);
});

test('the funnel and the stalled accounts read at a glance, each with the rule that put it there', function (): void {
    $page = visit('/admin/adopcion')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Por dónde se va la gente')
        ->assertSee('Prueba Conexión')
        ->assertSee('Sin negocio')
        ->assertSee('Creó el negocio y no cargó su catálogo')
        ->assertSee('Lleva 22 días en este paso')
        ->assertDontSee('Laboratorio Vida')
        ->screenshot(filename: 'adoption-desktop');

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0)
        ->and((int) $page->script(ADOPTION_TABLE_VS_CARD_JS))->toBeLessThanOrEqual(0);
});

test('the three tabs are one list cut three ways, each with its own count', function (): void {
    $page = visit('/admin/adopcion')->resize(1280, 900)
        ->assertSee('Trabadas')
        ->click('Arrancando')
        ->assertSee('Marta Nueva')
        ->assertDontSee('Prueba Conexión')
        ->screenshot(filename: 'adoption-starting')
        ->click('Activas')
        ->assertSee('Laboratorio Vida')
        ->assertSee('Su asistente ya contesta')
        ->screenshot(filename: 'adoption-active');

    // Nobody to write to once the assistant answers. (The word is in the page's own
    // heading, so the check is on the link, not on the text.)
    expect((bool) $page->script('document.querySelector("a[href^=\'mailto:\']") === null'))->toBeTrue();
});

test('a row opens the business file, or the mail written for its step when there is no business', function (): void {
    $page = visit('/admin/adopcion')->resize(1280, 900);

    // The mail the row without a business opens: her subject, the owner's name already in it.
    $href = (string) $page->script('document.querySelector("a[href^=\'mailto:\']").getAttribute("href")');

    expect($href)->toStartWith('mailto:')
        ->and(urldecode($href))->toContain('lara@aguirre.test')
        ->toContain('Hola Lara Aguirre')
        ->not->toContain('{nombre}');

    // The key cell, not the button: a click on the row goes where its action goes.
    $page->click('tr:has(a[href*="negocio="]) td.is-key')->waitForText('Cerrar la ficha');
    expect((string) $page->script('location.search'))->toContain('negocio=');
});

test('writing to an account is noted on its row, so nobody writes to it twice', function (): void {
    visit('/admin/adopcion')->resize(1280, 900)
        ->click('a[href^="mailto:lara"]')
        ->waitForText('Le escribiste')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'adoption-written');
});

test('it reads in the dark and on a phone', function (): void {
    $dark = visit('/admin/adopcion')->resize(1280, 900)
        ->click('@theme-toggle')
        ->assertNoJavaScriptErrors()
        ->assertSee('Prueba Conexión')
        ->screenshot(filename: 'adoption-dark');

    expect((int) $dark->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    $phone = visit('/admin/adopcion')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertSee('Prueba Conexión')
        ->screenshot(filename: 'adoption-phone');

    expect((int) $phone->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
});
