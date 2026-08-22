<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
});

test('submitting an empty code shows the client-side required error', function (): void {
    // This is the regression that cost a full session: the Alpine submit() reads the value
    // via $wire.get('form.data.code'). If the DTO is not initialized (null), the read
    // is undefined and no error paints. This asserts the whole client-side path works.
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $this->actingAs($admin);

    $page = visit('/admin/catalogs');

    $page->click('Monedas')
        ->click('.catalog-toolbar .btn-primary')     // open the create form
        ->click('.catalog-form-foot .btn-primary')   // submit with the code empty
        ->assertSee('Este campo es obligatorio.')
        ->assertNoJavaScriptErrors();
});
