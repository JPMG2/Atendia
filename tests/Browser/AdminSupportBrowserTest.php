<?php

declare(strict_types=1);

use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Soporte — the shots a human looks at
|--------------------------------------------------------------------------
| The queue is a table with a status select in every row and an evidence
| sheet under the open one: exactly where a layout gives way. Measured at the
| three widths in both themes, with a late report, a settled one and one
| waiting for the business, and the evidence of the first one open.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.support.overdue_hours', 24);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $clinic = Business::factory()->create(['name' => 'Clínica Vida']);
    $salon = Business::factory()->create(['name' => 'Peluquería Lola']);

    $this->late = SupportTicket::factory()->for($clinic)->create([
        'body' => 'No me carga el catálogo de servicios y se queda en blanco cuando entro desde el teléfono, ya lo intenté tres veces.',
        'screen' => 'my-services',
        'created_at' => now()->subDays(2)->subHours(3),
        'context' => [
            'url' => 'https://atendia.test/servicios',
            'viewport' => '390x844',
            'agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'plan' => 'negocio',
            'locale' => 'es',
            'errors' => 'TypeError: Cannot read properties of undefined | 500 en /livewire/update',
        ],
    ]);

    SupportTicket::factory()->for($salon)->create([
        'kind' => SupportTicketKind::Idea,
        'body' => 'Sería útil poder ver el calendario por semana.',
        'screen' => 'agenda',
        'status' => SupportTicketStatus::Waiting,
        'created_at' => now()->subDays(6),
        'answered_at' => now()->subHours(5),
    ]);

    SupportTicket::factory()->resolved()->for($salon)->create([
        'body' => 'Una duda de facturación que ya se respondió.',
        'created_at' => now()->subDays(8),
        'resolved_at' => now()->subDays(8)->addHours(2),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the support queue holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.support', ['estado' => 'all']))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Soporte')
        ->assertSee('Clínica Vida')
        ->assertSee('2d 3h')
        ->click('@sup-expand-'.$this->late->id)
        ->assertSee('Safari 17 · iOS')
        ->assertSee('390×844 · teléfono')
        ->assertSee('El navegador registró 2 errores')
        ->screenshot(filename: 'admin-support-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    // The page not overflowing says nothing about the table: a column cut off
    // inside its own card hides behind a scrollbar nobody discovers.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .filter(w => w.offsetParent !== null)
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
