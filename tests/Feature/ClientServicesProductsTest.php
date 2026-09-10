<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\Menu;
use App\Models\SuggestedService;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Services and products screens — living mock-ups
|--------------------------------------------------------------------------
| Two states each, zero persistence: the pattern the home blessed. They pin
| the client-panel lock, the two-state switch, the offer-never-require copy
| and the menu children that lead here.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('both screens sit behind the client panel lock', function (): void {
    $this->get(route('my-services'))->assertRedirect(route('login'));
    $this->get(route('my-products'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('my-services'))->assertSuccessful()->assertSee(__('client.services.title'));
    $this->get(route('my-products'))->assertSuccessful()->assertSee(__('client.products.title'));
});

test('the services screen lists the offer and never demands price or duration', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSee(__('client.services.add'))
        ->assertSee('Corte de caballero')
        // A service without a price shows a dash, not a requirement.
        ->assertSee('—')
        ->assertSee(__('client.services.active'))
        ->assertSee(__('client.services.paused'))
        // Row actions live on the row itself — no modal to open first.
        ->assertSee(__('client.services.edit', ['name' => 'Corte de caballero']))
        ->assertSee(__('client.services.resume', ['name' => 'Peinado para eventos']));
});

test('the services screen offers the ai hand for the descriptions', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->assertSee(__('client.services.ai_title'))
        ->assertSee(__('client.services.ai_action'));
});

test('the empty services state teaches without inventing suggestions', function (): void {
    // No business yet means no trade: the chips section simply stays out.
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('goods.index')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.services.empty_title'))
        ->assertDontSee(__('client.services.suggestions'))
        ->assertDontSee(__('client.services.add_short'));
});

test('the suggestion chips speak the trade vocabulary straight from the catalog', function (): void {
    $user = User::factory()->create()->refresh();
    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    $activity = BusinessActivity::factory()->create();
    SuggestedService::factory()->create(['business_activity_id' => $activity->id, 'name' => 'Corte de dama']);
    $business->syncActivities($activity->id);

    $this->actingAs($user);

    Livewire::test('goods.index')
        ->assertSee('Corte de dama')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.services.suggestions'))
        ->assertSee('Corte de dama');
});

test('the products screen shows the pills and the import lane side by side', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->assertSee('Alternador Fiat Palio')
        ->assertSee(__('client.products.ai_title'))
        ->assertSee(__('client.products.ai_action'))
        ->assertSee(__('client.products.import_title'))
        ->assertSee(__('client.products.import_last', ['file' => 'repuestos-2026.xlsx', 'rows' => 152]));
});

test('the empty products state leads with the excel import', function (): void {
    $this->actingAs(User::factory()->create()->refresh());

    Livewire::test('products.index')
        ->call('switchTo', 'empty')
        ->assertSee(__('client.products.empty_title'))
        ->assertSee(__('client.products.import_cta'))
        ->assertSee(__('client.products.empty_or'));
});

test('the menu offers both screens as leaves — a child only joins when it earns its place', function (): void {
    $this->seed(MenuSeeder::class);
    $this->actingAs(User::factory()->create()->refresh());

    $this->get(route('dashboard'))
        ->assertSeeHtml(route('my-services'))
        ->assertSeeHtml(route('my-products'));

    expect(Menu::query()->where('label_key', 'menu.services')->firstOrFail()->children)->toHaveCount(0)
        ->and(Menu::query()->where('label_key', 'menu.products')->firstOrFail()->children)->toHaveCount(0);
});
