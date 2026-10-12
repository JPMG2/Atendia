<?php

declare(strict_types=1);

use App\Enums\ComplianceEventKind;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\ComplianceEvent;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The file of a business, in a real browser
|--------------------------------------------------------------------------
| The list is where she decides which business to look at, and the panel is
| where the fault and its answer have to read as one story. Measured at the
| three widths in both themes, with a business of each standing.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $suspended = Business::factory()->create(['name' => 'Lencería Dulce']);
    $suspended->forceFill(['suspended_at' => now()->subDay(), 'suspension_reason' => 'sexual'])->save();
    ComplianceEvent::factory()->of(ComplianceEventKind::ContentRejected, 'logo · sexual')->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDays(9)]);
    ComplianceEvent::factory()->of(ComplianceEventKind::Suspended, 'sexual')->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDays(8)]);
    ComplianceEvent::factory()->of(ComplianceEventKind::Appealed, 'Es un catálogo de lencería.')->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDays(7)]);
    ComplianceEvent::factory()->of(ComplianceEventKind::SuspensionLifted, 'sexual')->create(['business_id' => $suspended->id, 'actor_id' => $admin->id, 'occurred_at' => now()->subDays(6)]);
    ComplianceEvent::factory()->of(ComplianceEventKind::Suspended, 'repeat')->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDay()]);

    $late = Business::factory()->create(['name' => 'Kiosco El Faro']);
    $late->subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();
    ComplianceEvent::factory()->of(ComplianceEventKind::PaymentLate)->create(['business_id' => $late->id, 'occurred_at' => now()->subDays(2)]);

    $old = Business::factory()->create(['name' => 'Ferretería El Tornillo']);
    ComplianceEvent::factory()->of(ComplianceEventKind::PaymentRejected, 'El monto no coincide')->create(['business_id' => $old->id, 'occurred_at' => now()->subDays(200)]);

    $this->suspended = $suspended;
});

test('the list ranks by standing and a file opens with the fault and its answer', function (string $label, int $width, bool $dark): void {
    $page = visit('/admin/cumplimiento')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertSee('Cumplimiento')
        ->assertSee('Lencería Dulce')
        ->assertSee('Suspendido')
        ->assertSee('Pago atrasado')
        ->assertSee('En regla')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'compliance-list-'.$label)
        ->click('@compliance-open-'.$this->suspended->id)
        ->wait(1)
        ->assertSee('Suspensión levantada')
        ->assertSee('El negocio apeló la suspensión')
        ->assertSee('Es un catálogo de lencería.')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'compliance-file-'.$label);

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop' => ['desktop', 1280, false],
    'dark' => ['dark', 1280, true],
    'phone' => ['phone', 390, false],
]);
