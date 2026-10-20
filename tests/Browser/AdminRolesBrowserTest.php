<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Roles y permisos — the shots a human looks at
|--------------------------------------------------------------------------
| The matrix read as a shape: one block per area, so a job is understood at
| a glance instead of as forty checkboxes in a column.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    // A second role, so the list is a list and not one row.
    $design = Role::findOrCreate('diseno', 'web');
    $design->syncPermissions(['access-admin-panel', 'testimonials.moderate', 'moderation.view']);

    $person = User::factory()->create(['email_verified_at' => now()]);
    $person->assignRole('support');

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the roles screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.roles'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Roles y permisos')
        ->assertSee('No se edita')
        ->assertSee('Abre todo')
        ->screenshot(filename: 'admin-roles-'.$label.'-'.($dark ? 'dark' : 'light'));

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
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);

/** An element inside the screen block (row plus its actions) that carries this label. */
function inRow(string $label, string $selector): string
{
    $label = json_encode($label);

    return "[...document.querySelectorAll('.re-item')].find((item) => item.querySelector('.re-label').textContent.trim() === {$label}).querySelector('{$selector}')";
}

/** Clicks the switch of the editor row that carries this label. */
function flipRow(string $label): string
{
    $label = json_encode($label);

    return "[...document.querySelectorAll('.re-row')].find((row) => row.querySelector('.re-label').textContent.trim() === {$label}).querySelector('input').click()";
}

test('the editor paints the menu and answers every switch at once', function (): void {
    $role = Role::findByName('diseno');
    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 1100);

    $page->assertSee('Rol diseno')
        ->assertSee('Menú del panel de administración')
        ->assertSee('Siempre visible')
        ->assertSee('Sin cambios')
        ->assertNoJavaScriptErrors();

    // diseno opens two screens (Contenido, Testimonios) out of the 23 with a switch.
    expect($page->script("document.querySelector('.re-big span').textContent"))->toBe('2');

    // A group switch opens all its screens: Cobros has five.
    $page->script(flipRow('Cobros'));
    expect($page->script("document.querySelector('.re-big span').textContent"))->toBe('7');
    // Three distinct permissions move: Pagos and Cobranza share one, Consumo and Calidad another.
    $page->assertSee('3 cambios sin guardar')->screenshot(filename: 'admin-roles-editor-group');

    // One screen off leaves the group halfway, not off.
    $page->script(flipRow('Cobranza'));
    expect($page->script("[...document.querySelectorAll('.re-row')].find((row) => row.querySelector('.re-label').textContent.trim() === 'Cobros').querySelector('input').indeterminate"))->toBeTrue();
});

test('the editor saves what the switches say, actions included', function (): void {
    $role = Role::findByName('diseno');
    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 1100);

    $page->script(flipRow('Pagos'));
    $page->script(inRow('Pagos', '.re-expand').'.click()');
    $page->assertSee('Acreditar o rechazar un pago');
    $page->script(inRow('Pagos', '.re-act input').'.click()');
    $page->assertSee('2 cambios sin guardar')->screenshot(filename: 'admin-roles-editor-actions');

    $page->script("document.querySelector('.re-bar button[type=submit]').click()");
    $page->assertSee('Sin cambios')->assertNoJavaScriptErrors();

    $role->refresh();

    expect($role->hasPermissionTo('payments.view'))->toBeTrue()
        ->and($role->hasPermissionTo('payments.verify'))->toBeTrue()
        ->and($role->hasPermissionTo('testimonials.moderate'))->toBeTrue();
});

test('closing a screen takes its actions with it', function (): void {
    $role = Role::findByName('diseno');
    $role->givePermissionTo(['payments.view', 'payments.verify']);
    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 1100);

    $page->script(flipRow('Pagos'));
    $page->script("document.querySelector('.re-bar button[type=submit]').click()");
    $page->assertSee('Sin cambios');

    $role->refresh();

    // Cobranza shares the permission: it closes with Pagos, and the action goes with both.
    expect($role->hasPermissionTo('payments.view'))->toBeFalse()
        ->and($role->hasPermissionTo('payments.verify'))->toBeFalse();
});

test('the editor holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $role = Role::findByName('support');
    $page = visit(route('admin.roles.edit', $role->id))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertSee('Rol support')
        ->assertSee('Quién tiene este rol')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-roles-editor-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);

    // No row of the menu may push past its card.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".re-menu, .re-sum")).map(c => c.scrollWidth - c.clientWidth).reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);

test('a template fills the switches of a new role and the preview shows its menu', function (): void {
    $page = visit(route('admin.roles.create'))->resize(1280, 1100);

    $page->script("document.querySelector('#if-starter').focus()");
    $page->script("[...document.querySelectorAll('.combo [role=option], .combo li')].find((o) => o.textContent.includes('Cobranza')).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))");
    $page->wait(2);

    // Pagos, Cobranza share one permission, and Negocios · Todos opens with businesses.view.
    $page->assertSee('cambios sin guardar')->assertNoJavaScriptErrors();

    $page->script("[...document.querySelectorAll('.re-link')].find((b) => b.textContent.includes('Ver como')).click()");
    $page->wait(1)->assertSee('Así ve el menú este rol')->screenshot(filename: 'admin-roles-editor-preview');

    $shown = $page->script("[...document.querySelectorAll('.re-preview .re-pv')].filter((n) => n.offsetParent !== null).map((n) => n.textContent.trim()).join('|')");

    expect($shown)->toContain('Pagos')->toContain('Inicio')->not->toContain('Contenido');
});

test('the preview marks what a save changes and says who it reaches', function (): void {
    $role = Role::findByName('support');
    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 1100);

    $page->script(flipRow('Soporte'));
    $page->script(flipRow('Pagos'));
    $page->script("[...document.querySelectorAll('.re-link')].find((b) => b.textContent.includes('Ver como')).click()");
    $page->wait(1)
        ->assertSee('Al guardar, quita Soporte a 1 persona.')
        ->assertSee('Al guardar, suma Pagos, Cobranza a 1 persona.')
        ->assertSee('Se oculta')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-roles-editor-impact');

    $gone = $page->script("[...document.querySelectorAll('.re-pv-gone .re-pv-name')].map((n) => n.textContent.trim()).join('|')");

    expect($gone)->toBe('Soporte');
});

test('taking a screen from people who use it asks first, and cancelling keeps the role', function (): void {
    $role = Role::findByName('support');
    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 1100);

    $page->script(flipRow('Soporte'));
    $page->script("document.querySelector('.re-bar button[type=submit]').click()");
    $page->wait(1)
        ->assertSee('Quitar pantallas a quien las usa')
        ->assertSee('Al guardar, quita Soporte a 1 persona.')
        ->screenshot(filename: 'admin-roles-editor-confirm');

    // Cancel: nothing is written.
    $page->script('document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }))');
    $page->wait(1);
    expect($role->refresh()->hasPermissionTo('support.view'))->toBeTrue();

    $page->script("document.querySelector('.re-bar button[type=submit]').click()");
    $page->wait(1);
    $page->script('document.querySelector("[x-ref=accept]").click()');
    $page->wait(2)->assertSee('Sin cambios')->assertNoJavaScriptErrors();

    expect($role->refresh()->hasPermissionTo('support.view'))->toBeFalse();
});

test('the role history and the people filtered by role are readable', function (): void {
    $role = Role::findByName('support');
    $role->syncPermissions(['access-admin-panel', 'support.view']);
    User::factory()->create(['name' => 'Ana Perez', 'email_verified_at' => now()])->assignRole($role);

    $page = visit(route('admin.roles.edit', $role->id))->resize(1280, 900);
    $page->script("document.querySelector('[data-testid=role-history]').click()");
    $page->wait(1)->assertSee('Historial del rol support')->assertSee('Se lo asignó a Ana Perez')->assertNoJavaScriptErrors()->screenshot(filename: 'admin-roles-editor-history');

    $people = visit(route('admin.users', ['rol' => 'support']))->resize(1280, 900);
    $people->assertSee('Usuarios')->assertNoJavaScriptErrors()->screenshot(filename: 'admin-users-by-role');
});

test('a new role starts empty and asks for its name', function (): void {
    $page = visit(route('admin.roles.create'))->resize(1280, 900);

    $page->assertSee('Nuevo rol')
        ->assertSee('Clave del rol')
        ->assertSee('Rol nuevo sin guardar')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-roles-editor-new');
});
