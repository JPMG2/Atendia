<?php

declare(strict_types=1);

use App\Actions\Admin\StartModelEval;
use App\Classes\Main\AiEvalResults;
use App\Classes\Main\TaskCosts;
use App\Enums\AiCapability;
use App\Models\AiModel;
use App\Models\AiTask;
use App\Models\AiUsage;
use App\Models\User;
use Database\Seeders\AiModelSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Modelos de IA: what a task costs, what has no price, and the button that measures
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(AiModelSeeder::class);
    File::delete(AiEvalResults::runningPath());

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

afterEach(fn () => File::delete(AiEvalResults::runningPath()));

/** A cheaper model, priced from the first of the year so this month's calls fall under it. */
function toolsCheaperModel(): AiModel
{
    return AiModel::create([
        'provider' => 'openai', 'capability' => AiCapability::Text, 'code' => 'modelo-barato', 'label' => 'Barato',
        'effective_from' => '2026-01-01', 'prompt_per_million' => 1, 'cached_per_million' => 0.1, 'completion_per_million' => 2,
    ]);
}

test('a task\'s month is repriced at another model with the same tokens', function (): void {
    toolsCheaperModel();
    AiUsage::create(['kind' => 'FaqDrafter', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000, 'cached_tokens' => 0, 'output_tokens' => 1_000_000]);

    $costs = new TaskCosts(now());

    expect($costs->actual('FaqDrafter')->calls)->toBe(1)
        ->and($costs->actual('FaqDrafter')->cost)->toBeGreaterThan(0.0)
        ->and($costs->with('FaqDrafter', 'modelo-barato'))->toBe(3.0)
        ->and($costs->with('FaqDrafter', 'modelo-barato'))->toBeLessThan($costs->actual('FaqDrafter')->cost);
});

test('a task that made no calls has no cost, not a cost of zero', function (): void {
    $costs = new TaskCosts(now());

    expect($costs->actual('FaqDrafter'))->toBeNull()
        ->and($costs->with('FaqDrafter', 'gpt-6-astra'))->toBeNull();
});

test('a model that runs without a catalog price is flagged on its row', function (): void {
    // The audio task runs on the package's default, which the catalog does not price.
    Livewire::test('admin.ai.index')->assertSee('Sin precio');
});

test('the picked model says what the month would have cost, and the saving', function (): void {
    toolsCheaperModel();
    AiUsage::create(['kind' => 'FaqDrafter', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000, 'cached_tokens' => 0, 'output_tokens' => 1_000_000]);

    config(['ai.providers.openai.key' => 'k', 'ai.providers.openai-app.key' => 'k']);

    $faq = AiTask::query()->where('key', 'FaqDrafter')->firstOrFail();

    Livewire::test('admin.ai.index')
        ->set('tasks.model.'.$faq->id, 'openai|modelo-barato')
        ->assertSee('Con este: USD 3,0000');
});

test('measuring a model starts one detached run with its own key and leaves a marker', function (): void {
    // The real case of 2026-10-08: two suites hung for 22 hours must not count as "busy".
    Process::fake([
        '*etime,args*' => Process::result(output: "ELAPSED COMMAND\n22h01 php ./vendor/bin/pest tests/Browser/SupportBrowserTest.php\n21h51 php ./vendor/bin/pest tests/Browser/PanelResponsiveBrowserTest.php\n"),
        '*' => Process::result(),
    ]);
    config(['ai.providers.openai.key' => 'k']);

    app(StartModelEval::class)->handle('gpt-6-astra');

    // The shell line is a string; the process-list guard before it is an array command.
    Process::assertRan(fn ($process): bool => is_string($process->command)
        && str_contains($process->command, 'setsid')
        && str_contains($process->command, 'EVAL_MODEL=')
        && str_contains($process->command, 'gpt-6-astra')
        && str_contains($process->command, 'MechanicalEvalTest'));
    expect(AiEvalResults::running()->code)->toBe('gpt-6-astra');
});

test('a second run is refused while one is going', function (): void {
    Process::fake();
    config(['ai.providers.openai.key' => 'k']);
    AiEvalResults::markRunning('gpt-6-astra', 'openai');

    expect(fn () => app(StartModelEval::class)->handle('gpt-6-astra'))->toThrow(DomainException::class, 'busy');
});

test('a suite that started minutes ago does make the run wait, because both reset the same test database', function (): void {
    Process::fake([
        '*etime,args*' => Process::result(output: "ELAPSED COMMAND\n04:12 php ./vendor/bin/pest tests/Browser/AdminAiBrowserTest.php\n"),
        '*' => Process::result(),
    ]);
    config(['ai.providers.openai.key' => 'k']);

    expect(fn () => app(StartModelEval::class)->handle('gpt-6-astra'))->toThrow(DomainException::class, 'busy');
    expect(AiEvalResults::running())->toBeNull();
});

test('the age ps prints is read in both spellings', function (string $line, int $minutes): void {
    expect(StartModelEval::ageInMinutes($line))->toBe($minutes);
})->with([
    'busybox minutes' => ['04:12 php vendor/bin/pest', 4],
    'busybox hours' => ['22h01 php vendor/bin/pest', 22 * 60 + 1],
    'busybox days' => ['1d01 php vendor/bin/pest', 1440 + 60],
    'procps hours' => ['02:30:10 php vendor/bin/pest', 150],
    'procps days' => ['2-03:00:00 php vendor/bin/pest', 2 * 1440 + 180],
    'unreadable counts as young' => ['??? php vendor/bin/pest', 0],
]);

test('only a model that answers in text can be measured, and only through a key that exists', function (): void {
    Process::fake();
    // The .env's own keys must not leak in: "no key" is the case under test.
    config(['ai.providers.openai.key' => null, 'ai.providers.openai-app.key' => null]);

    expect(fn () => app(StartModelEval::class)->handle('text-embedding-3-small'))->toThrow(DomainException::class, 'not_measurable')
        ->and(fn () => app(StartModelEval::class)->handle('gpt-6-astra'))->toThrow(DomainException::class, 'no_key');
});
