<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Consumo de IA (admin)
|--------------------------------------------------------------------------
| The one screen that answers whether a client of 29 USD is eating 40. It
| adds nothing of its own: it reads the meter and values it at the price the
| model had THAT month, and says so when there is no published price —
| because a zero there would read as "that client was free".
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function spendAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function astraPriced(float $prompt = 2.0): AiModel
{
    return AiModel::create([
        'provider' => 'openai', 'code' => 'gpt-6-astra', 'label' => 'GPT-6 Astra',
        'effective_from' => now()->startOfMonth()->subYear(),
        'prompt_per_million' => $prompt, 'cached_per_million' => 0.5, 'completion_per_million' => 10.0,
    ]);
}

test('the screen opens for the admin and closes for a client', function (): void {
    spendAdmin();
    $this->get(route('admin.ai-usage'))->assertOk();

    Auth::logout();
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate(Business::factory()->create())->save();

    $this->actingAs($client->refresh())->get(route('admin.ai-usage'))->assertForbidden();
});

test('the screen renders with nothing metered instead of breaking', function (): void {
    spendAdmin();

    Livewire::test('admin.ai-usage.index')
        ->assertOk()
        ->assertSee('Nada medido en este mes');
});

test('each business is billed at the price its model had that month', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    // $2 of input + $1 of cached + $1 of output.
    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'cached_tokens' => 2_000_000, 'output_tokens' => 100_000,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Laboratorio Vida')
        ->assertSee('$4,00')
        ->assertSee('AsistenteAtendia');
});

test('a call with no published price shows no cost instead of a zero', function (): void {
    spendAdmin();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'modelo-sin-precio',
        'input_tokens' => 1_000_000, 'output_tokens' => 100_000,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Laboratorio Vida')
        ->assertSee('su modelo no tiene precio publicado')
        ->assertDontSee('$0,00');
});

test('a call with no business is charged to the platform, not to a client', function (): void {
    spendAdmin();
    astraPriced();

    AiUsage::query()->create([
        'business_id' => null, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Plataforma')
        ->assertSee('$2,00');
});

test('clearing the month picker falls back to this month instead of breaking', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->set('month', '')
        ->assertOk()
        ->assertSee('Laboratorio Vida');
});

test('the figures are written the way this app writes every number', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_200_000, 'output_tokens' => 0,
    ]);

    // A thousands separator read in English beside a price read in Spanish is
    // two number systems on one row.
    Livewire::test('admin.ai-usage.index')
        ->assertSee('1.200.000')
        ->assertDontSee('1,200,000');
});

test('choosing another month changes what is counted', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('$2,00')
        ->set('month', now()->startOfMonth()->subMonth()->format('Y-m'))
        ->assertSee('Nada medido en este mes')
        ->assertDontSee('Laboratorio Vida');
});
