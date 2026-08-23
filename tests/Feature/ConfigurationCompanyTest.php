<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** An admin, which is what the whole screen is gated behind. */
function companyAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('a guest is redirected to login from the company page', function (): void {
    $this->get('/admin/company')->assertRedirect(route('login'));
});

test('a client cannot reach the company page', function (): void {
    $client = User::factory()->create(); // the factory assigns the client role

    $this->actingAs($client)->get('/admin/company')->assertForbidden();
});

test('an admin sees the company screen with both tabs', function (): void {
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertOk()
        ->assertSee(__('company.title'))
        ->assertSee('<title>Compañía</title>', false)
        ->assertSee(__('company.tabs.data'))
        ->assertSee(__('company.tabs.contact'));
});

test('the company data tab is the one that opens first', function (): void {
    // The tab bar seeds Alpine with the active tab, so the default is what the
    // markup says and not whichever panel happens to render first.
    $this->actingAs(companyAdmin())->get('/admin/company')
        ->assertSee("tab: 'data'", false);
});

test('every field of the company screen is rendered', function (): void {
    $response = $this->actingAs(companyAdmin())->get('/admin/company');

    // Each column of `companies` needs somewhere to be typed, or the screen
    // silently drops a field that the invoice header depends on.
    foreach ([
        'legal_name', 'tagline', 'tax_id', 'tax_condition_id', 'region_id',
        'address', 'logo_path_light', 'logo_path_dark', 'text_copyright',
        'email', 'phone', 'web',
    ] as $column) {
        $response->assertSee($column, false);
    }
});

test('the company screen uses no raw form control', function (): void {
    // Golden rule: fields come from <x-ui.*>/<x-inputsform.*>, which is what
    // guarantees the single focus ring, the theming and the error wiring.
    $blade = file_get_contents(
        resource_path('views/components/configuration/⚡company.blade.php')
    );

    expect($blade)->not->toMatch('/<(input|select|textarea)\b/');
});

test('the company option hangs from the settings item of the admin menu', function (): void {
    // It is not an area of its own: these are AtendIa's own data, so the option
    // lives inside Configuración and not next to it.
    $this->seed(MenuSeeder::class);

    $settings = Menu::query()->where('label_key', 'menu.admin_settings')->sole();

    $this->assertDatabaseHas('menus', [
        'panel' => 'admin',
        'parent_id' => $settings->id,
        'label_key' => 'menu.admin_company',
        'route_name' => 'admin.company',
        'icon' => 'building-2',
    ]);

    // The icon has to exist in the central registry or <x-icon> draws nothing.
    expect(config('icons.building-2'))->not->toBeNull();
});
