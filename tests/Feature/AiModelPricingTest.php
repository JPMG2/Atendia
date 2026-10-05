<?php

declare(strict_types=1);

use App\Ai\Agents\FaqDrafter;
use App\Models\AiModel;
use App\Models\AiTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The price belongs to the call, not to today
|--------------------------------------------------------------------------
| Born 2026-10-03, from her question: "what happens if tomorrow I change the
| model?". The cost report read ONE global rate, so the first model change
| would have revalued every past call at the new price — a past that never
| happened. The rate is now looked up by model and by date.
*/

test('a call keeps the price its model had that day', function (): void {
    AiModel::create([
        'provider' => 'openai', 'code' => 'modelo-viejo', 'label' => 'Viejo', 'effective_from' => '2026-01-01',
        'prompt_per_million' => 10, 'cached_per_million' => 1, 'completion_per_million' => 50,
    ]);

    AiModel::create([
        'provider' => 'openai', 'code' => 'modelo-viejo', 'label' => 'Viejo', 'effective_from' => '2026-06-01',
        'prompt_per_million' => 30, 'cached_per_million' => 3, 'completion_per_million' => 90,
    ]);

    $march = AiModel::rateOn('modelo-viejo', Carbon\Carbon::parse('2026-03-15'));
    $july = AiModel::rateOn('modelo-viejo', Carbon\Carbon::parse('2026-07-15'));

    expect((float) $march->prompt_per_million)->toBe(10.0)
        ->and((float) $july->prompt_per_million)->toBe(30.0);
});

test('a date before any published price has no rate instead of a wrong one', function (): void {
    AiModel::create([
        'provider' => 'openai', 'code' => 'modelo-nuevo', 'label' => 'Nuevo', 'effective_from' => '2026-06-01',
        'prompt_per_million' => 30, 'cached_per_million' => 3, 'completion_per_million' => 90,
    ]);

    expect(AiModel::rateOn('modelo-nuevo', Carbon\Carbon::parse('2026-01-15')))->toBeNull();
});

/*
| The assignment half: which model a task runs on, and on WHICH provider, is
| a row. `ia-economia-tokens.md` §8 asks for trying a cheap model by measuring;
| the code could only change the name, which it then sent to OpenAI anyway.
*/
function priced(string $provider, string $code): AiModel
{
    return AiModel::create([
        'provider' => $provider, 'code' => $code, 'label' => $code, 'effective_from' => '2026-01-01',
        'prompt_per_million' => 1, 'cached_per_million' => 1, 'completion_per_million' => 1,
    ]);
}

test('a task with no model assigned leaves the agent on the pair it declares', function (): void {
    AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    expect(AiTask::ladderFor('FaqDrafter'))->toBeNull()
        ->and((new FaqDrafter)->provider())->toBe(['openai' => 'gpt-6-astra']);
});

test('assigning a model to a task is a row, not a deploy', function (): void {
    priced('openai', 'modelo-barato');
    AiTask::create([
        'key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes', 'model_code' => 'modelo-barato',
    ]);

    expect((new FaqDrafter)->provider())->toBe(['openai' => 'modelo-barato']);
});

test('a model of another provider is called on that provider, not on OpenAI', function (): void {
    priced('anthropic', 'modelo-de-otro-lab');
    AiTask::create([
        'key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes', 'model_code' => 'modelo-de-otro-lab',
    ]);

    expect((new FaqDrafter)->provider())->toBe(['anthropic' => 'modelo-de-otro-lab']);
});

test('a fallback on another provider becomes the second attempt', function (): void {
    priced('openai', 'modelo-primero');
    priced('anthropic', 'modelo-respaldo');
    AiTask::create([
        'key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes',
        'model_code' => 'modelo-primero', 'fallback_model_code' => 'modelo-respaldo',
    ]);

    expect((new FaqDrafter)->provider())->toBe([
        'openai' => 'modelo-primero',
        'anthropic' => 'modelo-respaldo',
    ]);
});

test('a fallback on the same provider is dropped instead of overwriting the first', function (): void {
    priced('openai', 'modelo-primero');
    priced('openai', 'modelo-hermano');
    AiTask::create([
        'key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes',
        'model_code' => 'modelo-primero', 'fallback_model_code' => 'modelo-hermano',
    ]);

    expect((new FaqDrafter)->provider())->toBe(['openai' => 'modelo-primero']);
});

test('a model with no published price is not called at all', function (): void {
    AiTask::create([
        'key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes', 'model_code' => 'modelo-sin-precio',
    ]);

    expect(AiTask::ladderFor('FaqDrafter'))->toBeNull()
        ->and((new FaqDrafter)->provider())->toBe(['openai' => 'gpt-6-astra']);
});
