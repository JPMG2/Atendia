<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| "Mis pagos" and the admin desk in a real browser: clean console, the
| period ring, the reminder banner and the review queue on screen.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

test('mis pagos shows the period, the next payment, the banner and the history', function (): void {
    Company::factory()->create(['payment_instructions' => "Banco Nación · Cuenta corriente\nTitular: Atendia SRL\nAlias: atendia.pagos"]);
    $business = Business::factory()->create(['name' => 'Laboratorio Vida', 'tax_id' => '30-71234567-9']);
    $business->subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(5)])->save();
    Payment::factory()->paid()->create(['business_id' => $business->id, 'created_at' => now()->subMonth()]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    visit('/pagos')
        ->assertNoJavaScriptErrors()
        ->assertSee('Tu próximo pago es en 5 días')
        ->assertSee('atendia.pagos')
        ->screenshot(fullPage: true, filename: 'billing-my-payments');
});

test('the admin desk lists the receipts waiting for review', function (): void {
    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    Payment::factory()->create(['business_id' => $business->id, 'reference' => '00012345']);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);

    visit('/admin/pagos')
        ->assertNoJavaScriptErrors()
        ->assertSee('Laboratorio Vida')
        ->assertSee('00012345')
        ->screenshot(fullPage: true, filename: 'billing-admin-desk');
});
