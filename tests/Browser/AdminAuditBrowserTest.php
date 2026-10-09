<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Auditoría — the shots a human looks at
|--------------------------------------------------------------------------
| The strong changes painted, the ordinary ones out of the way, and the
| person as the filter — because the question is always about somebody.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Activity::query()->delete();

    $admin = User::factory()->create(['name' => 'Juan', 'email' => 'admin@atendia.test', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    Activity::query()->delete();

    $this->actingAs($admin->refresh());

    // One of each kind the screen tells apart.
    Business::factory()->create(['name' => 'Kiosco La Esquina']);
    Business::factory()->create(['name' => 'Panadería del Centro'])->delete();

    $person = User::factory()->create(['name' => 'Rocío Paz', 'email' => 'rocio@atendia.test', 'email_verified_at' => now()]);
    $person->assignRole('support');

    // And something nobody signed in did.
    Auth::logout();
    Business::factory()->create(['name' => 'Alta automática']);
    $this->actingAs($admin);
});

test('the audit screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.audit'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Auditoría')
        // A deletion and an access change: what she must not have to hunt for.
        ->assertSee('Panadería del Centro')
        ->assertSee('Dio acceso')
        // The ordinary creation stays out until she asks for it.
        ->assertDontSee('Kiosco La Esquina')
        ->screenshot(filename: 'admin-audit-'.$label.'-'.($dark ? 'dark' : 'light'));

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

test('filtering by the two-factor reset leaves only those entries', function (): void {
    $person = User::query()->where('name', 'Rocío Paz')->sole();
    activity('access')->performedOn($person)->causedBy(auth()->user())->withProperties(['names' => []])->log('two_factor_reset');

    $page = visit(route('admin.audit', ['accion' => 'two_factor_reset']))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Restableció el doble factor')
        ->assertDontSee('Panadería del Centro')
        ->screenshot(filename: 'admin-audit-action-filter');
});

test('the chips of the active filters show, and dropping one clears its field too', function (): void {
    $person = User::query()->where('name', 'Rocío Paz')->sole();
    activity('access')->performedOn($person)->causedBy(auth()->user())->withProperties(['names' => []])->log('two_factor_reset');

    $page = visit(route('admin.audit', ['quien' => auth()->id(), 'accion' => 'two_factor_reset']))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Quién: Juan')
        ->assertSee('Limpiar todo')
        ->screenshot(filename: 'admin-audit-chips');

    $page->click('.filter-chips-clear')->assertDontSee('Limpiar todo')->assertSee('Panadería del Centro')
        ->screenshot(filename: 'admin-audit-chips-cleared');

    // The field must say what the list says: no stale name left in the combobox.
    expect($page->script('document.querySelector(".form-row").innerText'))->not->toContain('Restableció el doble factor');
});

test('the keys take her to the person field and clear the filters, never while typing', function (): void {
    $page = visit(route('admin.audit', ['accion' => 'deleted']))->resize(1280, 900);

    $press = fn (string $key) => $page->script("document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key: '{$key}', bubbles: true}))");

    $press('/');
    expect($page->script('document.activeElement.getAttribute("role")'))->toBe('combobox');

    // Inside a field the same key is just a character: nothing is cleared.
    $press('c');
    $page->assertSee('Qué pasó: Eliminó');

    $page->script('document.activeElement.blur()');
    $press('c');

    $page->assertDontSee('Qué pasó: Eliminó')->screenshot(filename: 'admin-audit-keys');
});

test('an access change with dozens of names stays one line tall until it is opened', function (): void {
    $names = collect(range(1, 39))->map(fn (int $n): string => 'permission-number-'.$n)->all();
    Activity::query()->where('log_name', 'access')->latest('id')->firstOrFail()->update(['properties' => ['names' => $names]]);

    $page = visit(route('admin.audit'))->resize(1280, 900);

    $page->assertSee('39 cambios')->assertDontSee('permission-number-39');

    $rowHeight = (int) $page->script('Math.max(...Array.from(document.querySelectorAll(".pay-table tbody tr")).map(r => r.getBoundingClientRect().height))');

    // Two text lines of the tallest cell (name plus email) is the ceiling; 39 names ran to ten.
    expect($rowHeight)->toBeLessThanOrEqual(80);

    $page->click('39 cambios')->assertSee('permission-number-39')->screenshot(filename: 'admin-audit-names-open');
});
