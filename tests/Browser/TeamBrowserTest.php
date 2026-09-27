<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Department;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed([RolesAndPermissionsSeeder::class, MenuSeeder::class]);

    $this->business = Business::factory()->create();
    $this->owner = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Carla Méndez', 'whatsapp' => '5491144550000']);

    $sales = Department::factory()->create(['business_id' => $this->business->id, 'name' => 'Ventas', 'routing_hint' => 'Quiere comprar, pide una cotización o un precio especial.']);
    Department::factory()->create(['business_id' => $this->business->id, 'name' => 'Pagos', 'routing_hint' => 'Reclama un cobro, manda un comprobante o pide un reintegro.', 'hours' => ['1' => ['09:00', '18:00'], '2' => ['09:00', '18:00'], '3' => ['09:00', '18:00'], '4' => ['09:00', '18:00'], '5' => ['09:00', '18:00']]]);

    $this->agent = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Martín Ruiz', 'whatsapp' => '5491155550101']);
    $this->agent->syncRoles(['agent']);
    $this->agent->departments()->sync([$sales->id]);

    TeamInvitation::factory()->create(['business_id' => $this->business->id, 'email' => 'sofia@shop.test']);
});

test('the owner sees the real team, invites, edits a department, and nothing overflows a phone', function (): void {
    $this->actingAs($this->owner);

    $page = visit('/equipo')->resize(1280, 900);

    $page->assertSee('Martín Ruiz')
        ->assertSee('sofia@shop.test')
        ->assertSee('lun a vie · 09:00–18:00')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'team-desktop')
        ->click(__('team.people.invite'))
        ->assertSee(__('team.invite.title'))
        ->screenshot(filename: 'team-invite')
        ->click(__('team.invite.cancel'))
        ->click('[aria-label="'.__('team.departments.edit', ['name' => 'Pagos']).'"]')
        ->assertSee(__('team.hours.label'))
        ->screenshot(filename: 'team-department-sheet')
        ->click(__('team.invite.cancel'));

    $page->resize(390, 844)
        ->screenshot(filename: 'team-mobile')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

test('an agent lands on the inbox with a menu of only what they work', function (): void {
    $this->actingAs($this->agent);

    visit('/dashboard')->resize(1280, 900)
        ->assertPathIs('/conversaciones')
        ->assertDontSee(__('menu.my_business'))
        ->assertDontSee(__('menu.plan_payments'))
        ->click('[data-testid="user-menu"]')
        ->assertSee(__('team.availability.label'))
        ->screenshot(filename: 'team-agent-inbox')
        ->assertNoJavaScriptErrors();
});

test('the invitation link opens a join page on the guest layout', function (): void {
    $token = 'browser-invitation-token';
    TeamInvitation::factory()->create(['business_id' => $this->business->id, 'email' => 'lucia@shop.test', 'token_hash' => TeamInvitation::hashToken($token)]);

    visit('/equipo/unirme/'.$token)->resize(1280, 900)
        ->assertSee(__('team.join.submit'))
        ->assertSee('lucia@shop.test')
        ->screenshot(filename: 'team-join')
        ->assertNoJavaScriptErrors();
});
