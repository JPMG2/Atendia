<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\SupportReply;
use App\Models\SupportTicketKind;
use App\Models\User;
use Database\Seeders\AdoptionNudgeSeeder;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportReplySeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The Soporte masters' three ideas, in a real browser
|--------------------------------------------------------------------------
| A report kind's glyph beside its name, a saved reply copied from its row
| without opening it, and each adoption message as a real account reads it.
*/

beforeEach(function (): void {
    app()->setLocale('es');

    foreach ([RolesAndPermissionsSeeder::class, CatalogFormSeeder::class, SupportReplySeeder::class,
        AdoptionNudgeSeeder::class, SupportTicketKindSeeder::class] as $seeder) {
        $this->seed($seeder);
    }

    SupportTicketKind::factory()->create(['code' => 'qwa', 'name' => 'Por WhatsApp', 'icon' => 'whatsapp', 'sort_order' => 40]);

    $owner = User::factory()->for(Business::factory()->create(['name' => 'Panadería Qxv']))->create(['name' => 'Ana Zelk']);
    $owner->assignRole('client');

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);
});

test('the three ideas show and work at every width, in both themes', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/catalogs')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    // The report kinds: every one carries its glyph beside its name.
    $page->click('Tipos de reporte')->wait(1)->assertNoJavaScriptErrors();
    expect((int) $page->script('[...document.querySelectorAll(".catalog-table tbody tr")].filter((r) => r.querySelector(".catalog-row-icon svg path, .catalog-row-icon svg rect, .catalog-row-icon svg circle")).length'))->toBe(4)
        // A brand logo is filled, as `<x-icon>` draws it; the others are stroked.
        ->and($page->script('[...document.querySelectorAll(".catalog-table tbody tr")].find((r) => r.textContent.includes("Por WhatsApp")).querySelector(".catalog-row-icon svg").getAttribute("fill")'))->toBe('currentColor')
        ->and($page->script('[...document.querySelectorAll(".catalog-table tbody tr")].find((r) => r.textContent.includes("Una idea")).querySelector(".catalog-row-icon svg").getAttribute("fill")'))->toBe('none');
    $page->screenshot(filename: 'catalog-support-kinds-'.$label);

    // The saved replies: copying says so for a moment and leaves the list where it was.
    $page->click('Respuestas guardadas')->wait(1)->assertNoJavaScriptErrors();
    // A stand-in clipboard that keeps what it is given, so the test reads WHAT was copied, not only the label.
    $page->script('Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText: (text) => { window.__copied = text; return Promise.resolve(); } } })');
    $page->script('document.querySelector(".catalog-table tbody tr button").click()');
    $page->wait(0.3);
    expect(trim((string) $page->script('document.querySelector(".catalog-table tbody tr button").textContent')))->toBe('Copiado')
        ->and($page->script('window.__copied'))->toBe(SupportReply::query()->orderBy('name')->value('body'))
        ->and((bool) $page->script('!!document.querySelector(".catalog-table tbody tr") && document.querySelector(".catalog-table tbody tr").offsetParent !== null'))->toBeTrue();
    $page->assertDontSee('Editar');
    $page->screenshot(filename: 'catalog-support-replies-'.$label);

    // The adoption messages: each active one with the real account's words in it, folded into one line.
    $page->click('Mensajes de adopción')->wait(1)->assertNoJavaScriptErrors()
        ->assertSee('Así le llega a un negocio real')
        ->assertPresent('@nudge-preview');
    $preview = (string) $page->script('document.querySelector("[data-testid=nudge-preview]").textContent');
    expect($preview)->toContain('Hola Ana Zelk,')
        ->and($preview)->not->toContain('{nombre}')
        ->and((int) $page->script('document.querySelectorAll("[data-testid=nudge-preview] li").length'))->toBe(5)
        ->and((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
    $page->screenshot(filename: 'catalog-support-nudges-'.$label);
})->with([
    'desktop-light' => ['desktop-light', 1280, false],
    'phone-dark' => ['phone-dark', 390, true],
]);
