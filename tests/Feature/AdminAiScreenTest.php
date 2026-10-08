<?php

declare(strict_types=1);

use App\Classes\Main\AiEvalResults;
use App\Classes\Main\AiPrices;
use App\Enums\AiCapability;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Models\AiTask;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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

function aiPriced(string $provider, string $code, AiCapability $capability = AiCapability::Text): AiModel
{
    return AiModel::create([
        'provider' => $provider, 'capability' => $capability, 'code' => $code, 'label' => strtoupper($code),
        'effective_from' => '2026-01-01', 'prompt_per_million' => 1,
        'cached_per_million' => 1, 'completion_per_million' => 1,
    ]);
}

/**
 * Three named connections with a key each: two OpenAI keys (clientes, fondo)
 * and one Anthropic, which is what the admin screen has to tell apart.
 */
function aiConnections(): void
{
    config([
        'ai.providers.openai-app.key' => 'test-key-a',
        'ai.providers.openai.key' => 'test-key-b',
        'ai.providers.anthropic.key' => 'test-key-c',
    ]);

    foreach (['openai-app' => 'OpenAI · app', 'openai' => 'OpenAI · general', 'anthropic' => 'Anthropic'] as $key => $label) {
        AiConnection::create(['key' => $key, 'label' => $label]);
    }
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
    aiConnections();
    aiPriced('openai', 'modelo-barato');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai|modelo-barato')
        ->call('assign')
        ->assertHasNoErrors();

    expect($task->refresh()->connection_key)->toBe('openai')
        ->and($task->model_code)->toBe('modelo-barato')
        ->and(AiTask::ladderFor('FaqDrafter'))->toBe(['openai' => 'modelo-barato']);
});

test('the same model behind two keys is two different choices', function (): void {
    aiConnections();
    aiPriced('openai', 'modelo-barato');

    expect(AiModel::assignable(AiCapability::Text))->toHaveKeys(['openai-app|modelo-barato', 'openai|modelo-barato'])
        ->and(AiModel::assignable(AiCapability::Text))->not->toHaveKey('anthropic|modelo-barato');
});

test('a fallback behind the same key is rejected instead of silently dropped', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-primero');
    aiPriced('openai', 'modelo-hermano');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai|modelo-primero')
        ->set("tasks.fallback.{$task->id}", 'openai|modelo-hermano')
        ->call('assign')
        ->assertHasErrors('fallback');

    expect($task->refresh()->fallback_model_code)->toBeNull();
});

test('a fallback behind the other key of the same lab is the second attempt', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-primero');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai-app|modelo-primero')
        ->set("tasks.fallback.{$task->id}", 'openai|modelo-primero')
        ->call('assign')
        ->assertHasNoErrors();

    expect(AiTask::ladderFor('FaqDrafter'))->toBe([
        'openai-app' => 'modelo-primero',
        'openai' => 'modelo-primero',
    ]);
});

test('a model of another lab can be the fallback', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-primero');
    aiPriced('anthropic', 'modelo-respaldo');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai-app|modelo-primero')
        ->set("tasks.fallback.{$task->id}", 'anthropic|modelo-respaldo')
        ->call('assign')
        ->assertHasNoErrors();

    expect(AiTask::ladderFor('FaqDrafter'))->toBe([
        'openai-app' => 'modelo-primero',
        'anthropic' => 'modelo-respaldo',
    ]);
});

test('a connection without a key offers nothing, and a lab model cannot be assigned through it', function (): void {
    aiAdmin();
    aiConnections();
    config(['ai.providers.openai.key' => null]);
    aiPriced('openai', 'modelo-barato');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    expect(AiModel::assignable(AiCapability::Text))->not->toHaveKey('openai|modelo-barato');

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai|modelo-barato')
        ->call('assign')
        ->assertHasErrors('model');
});

test('a task is only offered the models that can do its work', function (): void {
    aiConnections();
    aiPriced('openai', 'solo-texto');
    aiPriced('openai', 'con-fotos', AiCapability::Vision);
    aiPriced('openai', 'voz', AiCapability::Transcription);

    $forText = array_keys(AiModel::assignable(AiCapability::Text));
    $forVision = array_keys(AiModel::assignable(AiCapability::Vision));
    $forVoice = array_keys(AiModel::assignable(AiCapability::Transcription));

    // A model that sees can also read plain text, but not the other way round.
    expect($forText)->toContain('openai|solo-texto', 'openai|con-fotos')->not->toContain('openai|voz')
        ->and($forVision)->toContain('openai|con-fotos')->not->toContain('openai|solo-texto')
        ->and($forVoice)->toBe(['openai-app|voz', 'openai|voz']);
});

test('a task cannot be saved with a model that cannot do its work', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'voz', AiCapability::Transcription);
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai|voz')
        ->call('assign')
        ->assertHasErrors('model');

    expect($task->refresh()->model_code)->toBeNull();
});

test('the transcription task says the exact model it runs on today and is assigned apart', function (): void {
    aiAdmin();
    aiConnections();
    $task = AiTask::create(['key' => AiTask::TRANSCRIPTION, 'label' => 'Transcribe el audio', 'capability' => AiCapability::Transcription]);
    aiPriced('openai', 'voz-nueva', AiCapability::Transcription);

    $running = AiTask::board()->firstWhere('id', $task->id)['running'];
    expect(array_values($running)[0])->toBeString()->not->toBeEmpty();

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$task->id}", 'openai|voz-nueva')
        ->call('assign')
        ->assertHasNoErrors();

    expect(AiTask::ladderFor(AiTask::TRANSCRIPTION))->toBe(['openai' => 'voz-nueva']);
});

/** Points storage at a throwaway folder: a test must not write scores the real screen would then show. */
function aiEvalStorage(): void
{
    $original = app()->storagePath();
    $folder = sys_get_temp_dir().'/atendia-eval-'.uniqid();
    app()->useStoragePath($folder);

    test()->afterEach(function () use ($original, $folder): void {
        app()->useStoragePath($original);
        File::deleteDirectory($folder);
    });
}

test('a model says how the battery scored it, or that nobody measured it', function (): void {
    aiAdmin();
    aiConnections();
    aiEvalStorage();
    aiPriced('openai', 'medido');
    aiPriced('openai', 'sin-medir');
    aiPriced('openai', 'voz', AiCapability::Transcription);

    AiEvalResults::record('medido', 'openai-app', 68, 70);
    AiEvalResults::record('medido', 'openai-app', 25, 25, AiEvalResults::MECHANICAL);

    Livewire::test('admin.ai.index')
        ->assertSee('Conversación: 68 de 70')
        ->assertSee('Mecánicas: 25 de 25')
        ->assertSee('Sin medir con la batería');

    // The battery measures conversation: a voice model is never offered a score line.
    expect(substr_count(Livewire::test('admin.ai.index')->html(), 'Sin medir con la batería'))->toBe(1);
});

test('re-measuring a model replaces its score instead of stacking another', function (): void {
    aiEvalStorage();

    AiEvalResults::record('modelo', 'openai', 60, 70);
    AiEvalResults::record('modelo', 'openai', 68, 70);

    expect(AiEvalResults::measured())->toHaveCount(1)
        ->and(AiEvalResults::measured()['modelo']->passed)->toBe(68);
});

test('the embeddings row is shown locked, with the model it really uses', function (): void {
    aiAdmin();

    Livewire::test('admin.ai.index')
        ->assertSee((string) config('rag.embedding.model'))
        ->assertSee('no se cambia suelto');
});

test('the connections tab says which keys exist without showing them', function (): void {
    aiAdmin();
    aiConnections();

    Livewire::test('admin.ai.index')
        ->assertSee('OpenAI · app')
        ->assertSee('Con clave')
        ->assertDontSee('test-key-a');
});

test('one Guardar saves every task that changed, and only those', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-barato');

    $moved = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);
    $alsoMoved = AiTask::create(['key' => 'DigestWriter', 'label' => 'Arma el resumen del día']);
    $untouched = AiTask::create(['key' => 'ReplyTranslator', 'label' => 'Traduce una respuesta']);

    Livewire::test('admin.ai.index')
        ->set("tasks.model.{$moved->id}", 'openai|modelo-barato')
        ->set("tasks.model.{$alsoMoved->id}", 'openai|modelo-barato')
        ->call('assign')
        ->assertHasNoErrors();

    expect($moved->refresh()->model_code)->toBe('modelo-barato')
        ->and($alsoMoved->refresh()->model_code)->toBe('modelo-barato')
        // The row nobody touched is not written: a save that rewrites the whole
        // table makes every audit entry a lie about what she actually changed.
        ->and($untouched->refresh()->model_code)->toBeNull();
});

test('one choice fills every task of a group, and nothing is saved until Guardar', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-barato');
    $first = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes', 'is_mechanical' => true]);
    $second = AiTask::create(['key' => 'DigestWriter', 'label' => 'Arma el resumen del día', 'is_mechanical' => true]);

    Livewire::test('admin.ai.index')
        ->set('bulk.background', 'openai|modelo-barato')
        ->call('applyToGroup', 'background')
        ->assertSet("tasks.model.{$first->id}", 'openai|modelo-barato')
        ->assertSet("tasks.model.{$second->id}", 'openai|modelo-barato')
        ->assertDispatched('notify');

    // The choice is on screen only: a click that already saved would not leave her room to look.
    expect($first->refresh()->model_code)->toBeNull();
});

test('a group choice leaves alone the task whose work the model cannot do', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'solo-texto');
    $sees = AiTask::create(['key' => 'AsistenteAtendia', 'label' => 'Atiende a los clientes', 'capability' => AiCapability::Vision]);
    $reads = AiTask::create(['key' => 'AskAtendia', 'label' => 'Responde a la dueña']);

    Livewire::test('admin.ai.index')
        ->set('bulk.conversation', 'openai-app|solo-texto')
        ->call('applyToGroup', 'conversation')
        ->assertSet("tasks.model.{$reads->id}", 'openai-app|solo-texto')
        ->assertSet("tasks.model.{$sees->id}", '');
});

test('a group choice clears a fallback that would now sit behind the same key', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-barato');
    $task = AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes', 'is_mechanical' => true]);
    AiTask::create(['key' => 'DigestWriter', 'label' => 'Arma el resumen del día', 'is_mechanical' => true]);

    Livewire::test('admin.ai.index')
        ->set("tasks.fallback.{$task->id}", 'openai|modelo-barato')
        ->set('bulk.background', 'openai|modelo-barato')
        ->call('applyToGroup', 'background')
        ->assertSet("tasks.fallback.{$task->id}", '')
        ->call('assign')
        ->assertHasNoErrors();
});

test('saving with nothing changed says so instead of claiming a save', function (): void {
    aiAdmin();
    aiConnections();
    aiPriced('openai', 'modelo-barato');
    AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    Livewire::test('admin.ai.index')
        ->call('assign')
        ->assertHasNoErrors()
        ->assertDispatched('notify');
});

test('a voice model is priced by the minute, and a text one cannot skip its token prices', function (): void {
    aiAdmin();
    aiConnections();

    Livewire::test('admin.ai.index')
        ->call('create')
        ->set('model.provider', 'openai')
        ->set('model.capability', 'transcription')
        ->set('model.code', 'voz-nueva')
        ->set('model.label', 'Voz nueva')
        ->set('model.per_minute', '0.006')
        ->set('model.effective_from', '2026-11-01')
        ->call('store')
        ->assertHasNoErrors();

    $voice = AiModel::query()->where('code', 'voz-nueva')->firstOrFail();
    expect((float) $voice->per_minute)->toBe(0.006)
        ->and((float) $voice->prompt_per_million)->toBe(0.0);

    Livewire::test('admin.ai.index')
        ->call('create')
        ->set('model.provider', 'openai')
        ->set('model.capability', 'text')
        ->set('model.code', 'texto-nuevo')
        ->set('model.label', 'Texto nuevo')
        ->set('model.effective_from', '2026-11-01')
        ->call('store')
        ->assertHasErrors(['prompt_per_million', 'completion_per_million']);
});

test('the audio cost uses the price of the assigned voice model, and the config rate before that', function (): void {
    aiAdmin();
    aiConnections();
    $month = now()->startOfMonth();

    expect(app(AiPrices::class)->audioRate($month))->toBe((float) config('atendia.ai_rates.audio_per_minute'));

    $voice = aiPriced('openai', 'voz-nueva', AiCapability::Transcription);
    $voice->update(['per_minute' => 0.0125]);
    AiTask::create([
        'key' => AiTask::TRANSCRIPTION, 'label' => 'Transcribe', 'capability' => AiCapability::Transcription,
        'connection_key' => 'openai', 'model_code' => 'voz-nueva',
    ]);

    expect(app(AiPrices::class)->audioRate(now()))->toBe(0.0125);
});

test('a new price is a new row, so what was consumed keeps its own', function (): void {
    aiAdmin();
    aiConnections();
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
    aiConnections();
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
