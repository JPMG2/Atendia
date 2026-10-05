<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\AiTask;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Modelos de IA (admin)
|--------------------------------------------------------------------------
| Assigning a model used to mean tinker. What is worth testing here is that
| the row the agents read is written from the screen, that a fallback on the
| same lab cannot be saved (the ladder would drop it silently), and that the
| screen stays shut for a client.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function aiAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function aiPriced(string $provider, string $code): AiModel
{
    return AiModel::create([
        'provider' => $provider, 'code' => $code, 'label' => strtoupper($code),
        'effective_from' => '2026-01-01', 'prompt_per_million' => 1,
        'cached_per_million' => 1, 'completion_per_million' => 1,
    ]);
}

test('the screen opens for the admin and closes for a client', function (): void {
    aiAdmin();
    $this->get(route('admin.ai'))->assertOk();

    Auth::logout();
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate(Business::factory()->create())->save();

    $this->actingAs($client->refresh())->get(route('admin.ai'))->assertForbidden();
});

test('the screen renders with nothing loaded instead of breaking', function (): void {
    aiAdmin();

    Livewire::test('admin.ai.index')
        ->assertOk()
        ->assertSee('Todavía no hay tareas')
        ->assertSee('Todavía no hay modelos');
});

test('an unassigned task says what it runs on today', function (): void {
    aiAdmin();
    AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->assertSee('gpt-6-astra')
        ->assertSee('openai');
});

test('assigning a model writes the row the agents read', function (): void {
    aiAdmin();
    aiPriced('openai', 'modelo-barato');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'modelo-barato')
        ->call('assign', $task->id)
        ->assertHasNoErrors();

    expect($task->refresh()->model_code)->toBe('modelo-barato')
        ->and(AiTask::ladderFor('FaqDrafter'))->toBe(['openai' => 'modelo-barato']);
});

test('a fallback on the same lab is rejected instead of silently dropped', function (): void {
    aiAdmin();
    aiPriced('openai', 'modelo-primero');
    aiPriced('openai', 'modelo-hermano');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'modelo-primero')
        ->set("tasks.fallback.{$task->id}", 'modelo-hermano')
        ->call('assign', $task->id)
        ->assertHasErrors('fallback_model_code');

    expect($task->refresh()->fallback_model_code)->toBeNull();
});

test('a fallback on another lab becomes the second attempt', function (): void {
    aiAdmin();
    aiPriced('openai', 'modelo-primero');
    aiPriced('anthropic', 'modelo-respaldo');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'modelo-primero')
        ->set("tasks.fallback.{$task->id}", 'modelo-respaldo')
        ->call('assign', $task->id)
        ->assertHasNoErrors();

    expect(AiTask::ladderFor('FaqDrafter'))->toBe([
        'openai' => 'modelo-primero',
        'anthropic' => 'modelo-respaldo',
    ]);
});

test('a new price is a new row, so what was consumed keeps its own', function (): void {
    aiAdmin();
    aiPriced('openai', 'gpt-6-astra');

    Livewire::test('admin.ai.index')
        ->call('create')
        ->set('model.provider', 'openai')
        ->set('model.code', 'gpt-6-astra')
        ->set('model.label', 'GPT-6 Astra')
        ->set('model.prompt_per_million', '12')
        ->set('model.cached_per_million', '1.2')
        ->set('model.completion_per_million', '60')
        ->set('model.effective_from', '2026-11-01')
        ->call('store')
        ->assertHasNoErrors();

    expect(AiModel::query()->where('code', 'gpt-6-astra')->count())->toBe(2)
        ->and((float) AiModel::rateOn('gpt-6-astra', now())->prompt_per_million)->toBe(1.0)
        ->and((float) AiModel::rateOn('gpt-6-astra', now()->setDate(2026, 12, 1))->prompt_per_million)->toBe(12.0);
});

test('two prices of the same model cannot start the same day', function (): void {
    aiAdmin();
    aiPriced('openai', 'gpt-6-astra');

    Livewire::test('admin.ai.index')
        ->call('create')
        ->set('model.provider', 'openai')
        ->set('model.code', 'gpt-6-astra')
        ->set('model.label', 'GPT-6 Astra')
        ->set('model.prompt_per_million', '12')
        ->set('model.cached_per_million', '1.2')
        ->set('model.completion_per_million', '60')
        ->set('model.effective_from', '2026-01-01')
        ->call('store')
        ->assertHasErrors('code');
});
