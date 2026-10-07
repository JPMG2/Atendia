<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\RevenueSnapshot;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cobranza — the shots a human looks at
|--------------------------------------------------------------------------
| The thing to check by EYE is the order: the biggest account has to sit on
| top even though it is the one that lapsed most recently, because that is
| the call worth making today.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(PlanSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $rows = [
        ['Centro Odontológico Integral del Sur', 'premium', SubscriptionStatus::Paused, 4],
        ['Laboratorio Vida', 'negocio', SubscriptionStatus::PastDue, 12],
        ['Kiosco La Esquina', 'emprende', SubscriptionStatus::PastDue, 61],
        ['Panadería La Espiga', 'negocio', SubscriptionStatus::Active, -3],
    ];

    foreach ($rows as [$name, $plan, $status, $days]) {
        $business = Business::factory()->create(['name' => $name]);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => $plan,
            'billing_cycle' => 'monthly',
            'status' => $status,
            'current_period_ends_at' => now()->subDays($days),
        ]);
    }

    // Last month's photo, so the revenue tile has something to compare with.
    RevenueSnapshot::query()->create([
        'month' => CarbonImmutable::now()->subMonth()->startOfMonth()->toDateString(),
        'mrr' => 208,
        'paying' => 3,
        'trialing' => 0,
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the collection queue holds at every width and leads with the biggest account', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.collections'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Cobranza')
        ->assertSee('En riesgo por mes')
        ->assertSee('Centro Odontológico Integral del Sur')
        ->assertSee('Pausado')
        ->assertSee('hace 61 días')
        ->screenshot(filename: 'admin-collections-'.$label.'-'.($dark ? 'dark' : 'light'));

    // Worth first: the premium that lapsed on Friday above the small one that
    // has owed for two months.
    $firstRow = $page->text('.pay-table tbody tr:first-child');

    expect($firstRow)->toContain('Centro Odontológico Integral del Sur');

    // Only while the table IS a table: stacked under 991px the action gets a
    // line of its own, and sitting below the first cell is then correct.
    if ($width > 991) {
        $actionDrop = $page->script(
            'const row = document.querySelector(".pay-table tbody tr");
             const range = document.createRange();
             range.selectNodeContents(row.querySelector("td:first-child"));
             const text = range.getBoundingClientRect();
             const button = row.querySelector("td:last-child a, td:last-child button").getBoundingClientRect();
             Math.round((button.top + button.height / 2) - (text.top + text.height / 2))'
        );

        expect(abs((int) $actionDrop))->toBeLessThanOrEqual(3);
    }

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);
