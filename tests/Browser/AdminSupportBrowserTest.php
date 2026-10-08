<?php

declare(strict_types=1);

use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Models\Business;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
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

    SupportTicket::factory()->for($clinic)->create([
        'body' => 'Las respuestas del asistente tardan más de un minuto por la noche.',
        'screen' => 'assistant',
        'status' => SupportTicketStatus::Blocked,
        'created_at' => now()->subDays(2),
        'blocked_at' => now()->subDay(),
        'blocked_tried' => 'Reproducido a las 23:10: el análisis ocupa la cola del asistente.',
        'blocked_missing' => 'Que Desarrollo separe la cola del asistente de la del análisis.',
        'blocked_owner' => 'Desarrollo',
        'blocked_due' => today()->addDays(3),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    SupportReply::factory()->create(['name' => 'Reconectar el WhatsApp', 'body' => 'Entra a Conexión de WhatsApp, toca Reconectar y escanea el código.']);
    SupportReply::factory()->create(['name' => 'Ya lo arreglamos', 'body' => 'Ya lo arreglamos, prueba otra vez.']);

    SupportTicketMessage::factory()->note()->create([
        'support_ticket_id' => $this->late->id,
        'user_id' => $admin->id,
        'body' => 'Confirmado en logs: el job de análisis ocupa la cola.',
    ]);

    $this->actingAs($admin->refresh());
});

test('the support queue holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.support'))->resize($width, $height);
    $shot = fn (string $step) => 'admin-support-'.$step.'-'.$label.'-'.($dark ? 'dark' : 'light');

    // Nothing may widen the page, and nothing may be cut inside its own card:
    // a column hidden behind a scrollbar nobody discovers passes the first check.
    $assertFits = function () use (&$page): void {
        expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
        expect((int) $page->script(
            'Array.from(document.querySelectorAll(".pay-table-wrap, .slide-over-body"))
                .filter(w => w.offsetParent !== null)
                .map(w => w.scrollWidth - w.clientWidth)
                .reduce((a, b) => Math.max(a, b), 0)'
        ))->toBe(0);
    };

    if ($dark) {
        $page->click('@theme-toggle');
    }

    // 1. The queue: who has waited longest, with the late one painted.
    $page->assertNoJavaScriptErrors()
        ->assertSee('Por responder')
        ->assertSee('Clínica Vida')
        ->assertSee('2d 3h')
        ->assertSee('Más de 24 h')
        // The three conveniences: her own reports, and how long reports typically take.
        ->assertSee('Mis reportes')
        ->assertSee('Mediana en resolver, últimos 90 días')
        ->screenshot(filename: $shot('queue'));
    $assertFits();

    // 2. The report opens beside it: conversation, evidence in words, a composer.
    $page->click('@sup-expand-'.$this->late->id)
        ->assertSee('Conversación')
        ->assertSee('Notas internas')
        ->assertSee('Tu respuesta')
        ->assertSee('Respuesta guardada')
        ->click('Lo que capturamos')
        ->assertSee('Safari 17 · iOS')
        ->assertSee('390×844 · teléfono')
        ->assertSee('El navegador registró 2 errores')
        ->screenshot(filename: $shot('panel'));
    $assertFits();

    // 3. The customer: pays or not, WhatsApp, and where the answer lands.
    $page->click('Cliente')
        ->assertSee('Dónde le llega la respuesta')
        ->assertSee('WhatsApp del negocio')
        ->screenshot(filename: $shot('customer'));
    $assertFits();

    // 4. What the team told itself.
    $page->click('Notas internas')
        ->assertSee('Solo las ve el equipo')
        ->assertSee('Confirmado en logs')
        ->screenshot(filename: $shot('notes'));
    $assertFits();

    // 5. A report that could not be solved says what is missing.
    $page->click('No se pudo resolver…')
        ->assertSee('Qué probamos')
        ->assertSee('Qué falta para resolverlo')
        ->assertSee('Quién sigue')
        ->screenshot(filename: $shot('block'));
    $assertFits();
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);

test('an empty queue keeps its distance from the filters above it', function (): void {
    SupportTicket::query()->delete();

    $page = visit(route('admin.support'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('No hay reportes')
        ->screenshot(filename: 'admin-support-empty');

    // The defect was a block glued to the inputs: the gap is measured, not eyeballed.
    $gap = (float) $page->script(
        '(() => {
            const row = document.querySelector(".form-row").getBoundingClientRect();
            const empty = document.querySelector(".empty-state").getBoundingClientRect();
            return empty.top - row.bottom;
        })()'
    );

    expect($gap)->toBeGreaterThanOrEqual(16.0);
});

test('"my reports" narrows the queue to what is hers, with the button showing it is on', function (): void {
    $admin = User::query()->where('name', 'Administración de la plataforma')->sole();
    $this->late->update(['assigned_to' => $admin->id]);

    $page = visit(route('admin.support'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->click('Mis reportes')
        ->assertSee('No me carga el catálogo de servicios')
        ->assertAttribute('button[aria-pressed="true"]', 'aria-pressed', 'true')
        ->screenshot(filename: 'admin-support-mine');
});

test('the saved replies are a master in the catalogs hub, and the composer pastes them', function (): void {
    $this->seed(CatalogFormSeeder::class);

    $hub = visit(route('admin.catalogs'))->resize(1280, 900);

    $hub->assertNoJavaScriptErrors()
        ->click('Respuestas guardadas')
        ->assertSee('Reconectar el WhatsApp')
        ->assertSee('Crear respuesta')
        ->screenshot(filename: 'admin-support-replies-master');

    $page = visit(route('admin.support', ['reporte' => $this->late->id]))->resize(1280, 900);

    $page->assertSee('Respuesta guardada')
        ->screenshot(filename: 'admin-support-replies-composer');
});

test('the blocked queue says what is missing without opening anything', function (): void {
    $page = visit(route('admin.support', ['cola' => 'blocked']))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Que Desarrollo separe la cola del asistente')
        ->assertSee('Desarrollo')
        ->screenshot(filename: 'admin-support-blocked');
});
