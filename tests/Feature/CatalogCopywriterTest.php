<?php

declare(strict_types=1);

use App\Actions\Catalog\WriteMissingDescriptions;
use App\Ai\Agents\CatalogCopywriter;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Optimizar con IA" — the button that did nothing until 2026-09-30
|--------------------------------------------------------------------------
| It writes the descriptions a catalog is missing, in ONE call for the whole
| batch: a loop of calls is the same work at ten times the price. It only
| ever fills blanks — a line the owner wrote is never overwritten.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
});

test('it writes only the descriptions that are missing', function (): void {
    CatalogCopywriter::fake([[
        'items' => [
            ['name' => 'Limpieza dental', 'description' => 'Una limpieza completa en una sesión.'],
        ],
    ]]);

    $blank = Service::factory()->for($this->business)->create(['name' => 'Limpieza dental', 'description' => null]);
    $written = Service::factory()->for($this->business)->create(['name' => 'Blanqueamiento', 'description' => 'Lo que escribió la dueña']);

    $filled = app(WriteMissingDescriptions::class)->handle($this->business, collect([$blank, $written]));

    expect($filled)->toBe(1)
        ->and($blank->fresh()->description)->toBe('Una limpieza completa en una sesión.')
        ->and($written->fresh()->description)->toBe('Lo que escribió la dueña');
});

test('a catalog with nothing missing never reaches the model', function (): void {
    CatalogCopywriter::fake()->preventStrayPrompts();

    $service = Service::factory()->for($this->business)->create(['description' => 'Ya está escrita']);

    expect(app(WriteMissingDescriptions::class)->handle($this->business, collect([$service])))->toBe(0);
});

test('an answer that comes back reordered still describes the right item', function (): void {
    CatalogCopywriter::fake([[
        'items' => [
            ['name' => 'Ortodoncia', 'description' => 'Brackets y control mensual.'],
            ['name' => 'Limpieza dental', 'description' => 'Una limpieza completa en una sesión.'],
        ],
    ]]);

    $first = Service::factory()->for($this->business)->create(['name' => 'Limpieza dental', 'description' => null]);
    $second = Service::factory()->for($this->business)->create(['name' => 'Ortodoncia', 'description' => null]);

    app(WriteMissingDescriptions::class)->handle($this->business, collect([$first, $second]));

    expect($first->fresh()->description)->toBe('Una limpieza completa en una sesión.')
        ->and($second->fresh()->description)->toBe('Brackets y control mensual.');
});

test('the services screen writes them and says how many', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    CatalogCopywriter::fake([[
        'items' => [['name' => 'Limpieza dental', 'description' => 'Una limpieza completa.']],
    ]]);

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();
    Service::factory()->for($this->business)->create(['name' => 'Limpieza dental', 'description' => null]);

    Livewire::actingAs($user)
        ->test('goods.index')
        ->call('writeDescriptions')
        ->assertDispatched('notify');

    expect(Service::query()->where('business_id', $this->business->id)->value('description'))
        ->toBe('Una limpieza completa.');
});
