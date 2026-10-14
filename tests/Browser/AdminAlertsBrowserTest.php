<?php

declare(strict_types=1);

use App\Enums\PlatformEventKind;
use App\Models\Business;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tablero de avisos — the shots a human looks at
|--------------------------------------------------------------------------
| The thing to check by EYE is that the worst reads first: a critical line
| above an important one, and the red sensor above the green ones in its group.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $sensors = [
        ['queue', 'system', 'ok', 'OK', 'último trabajo hace 12 s'],
        ['disk', 'system', 'warn', '81 %', 'quedan 9 GB de 48 GB'],
        ['redis', 'system', 'ok', 'OK', 'responde en 1 ms'],
        ['whatsapp_links', 'customers', 'warn', '8 / 9', '1 sin conexión'],
        ['receipts', 'money', 'warn', '3', 'el más viejo, hace 1 día'],
        ['paused', 'money', 'crit', '1', 'dejaron de responder por falta de pago'],
        ['suspended', 'compliance', 'ok', '0', 'ninguno'],
    ];

    foreach ($sensors as [$key, $group, $status, $value, $detail]) {
        PlatformSensor::query()->create([
            'sensor_key' => $key, 'group' => $group, 'status' => $status, 'value' => $value,
            'detail' => $detail, 'checked_at' => now(), 'changed_at' => now(),
        ]);
    }

    $ferreteria = Business::factory()->create(['name' => 'Ferretería El Tornillo']);
    $lab = Business::factory()->create(['name' => 'Laboratorio Vida']);

    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessJoined)->create(['about_business_id' => $lab->id]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::ReceiptReceived)->create(['about_business_id' => $lab->id, 'detail' => 'Plan Negocio · USD 79,00']);
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create(['about_business_id' => $ferreteria->id]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::SystemDegraded)->create(['detail' => 'Disco · quedan 9 GB de 48 GB']);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the board holds at every width and leads with the worst', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.alerts'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Tablero de avisos')
        ->assertSee('Cola de trabajos')
        ->assertSee('Asistente pausado por falta de pago')
        ->assertSee('Ferretería El Tornillo')
        ->screenshot(filename: 'admin-alerts-'.$label.'-'.($dark ? 'dark' : 'light'));

    // Worst first: the critical line is above the important ones.
    expect($page->text('.pay-table tbody tr:first-child'))->toContain('Ferretería El Tornillo');

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');
    expect((int) $overflow)->toBeLessThanOrEqual(0);

    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );
    expect((int) $cut)->toBe(0);

    // A component opens into its detail: history strip, what to do, copy.
    $page->click('Cola de trabajos')
        ->assertSee('Qué hacer')
        ->assertSee('Copiar diagnóstico')
        ->screenshot(filename: 'admin-alerts-detail-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
