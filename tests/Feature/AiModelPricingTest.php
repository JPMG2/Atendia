<?php

declare(strict_types=1);

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
        'code' => 'modelo-viejo', 'label' => 'Viejo', 'effective_from' => '2026-01-01',
        'prompt_per_million' => 10, 'cached_per_million' => 1, 'completion_per_million' => 50,
    ]);

    AiModel::create([
        'code' => 'modelo-viejo', 'label' => 'Viejo', 'effective_from' => '2026-06-01',
        'prompt_per_million' => 30, 'cached_per_million' => 3, 'completion_per_million' => 90,
    ]);

    $march = AiModel::rateOn('modelo-viejo', Carbon\Carbon::parse('2026-03-15'));
    $july = AiModel::rateOn('modelo-viejo', Carbon\Carbon::parse('2026-07-15'));

    expect((float) $march->prompt_per_million)->toBe(10.0)
        ->and((float) $july->prompt_per_million)->toBe(30.0);
});

test('a date before any published price has no rate instead of a wrong one', function (): void {
    AiModel::create([
        'code' => 'modelo-nuevo', 'label' => 'Nuevo', 'effective_from' => '2026-06-01',
        'prompt_per_million' => 30, 'cached_per_million' => 3, 'completion_per_million' => 90,
    ]);

    expect(AiModel::rateOn('modelo-nuevo', Carbon\Carbon::parse('2026-01-15')))->toBeNull();
});

/*
| The orchestrator half: swapping the model a task runs on is a row, which is
| what `ia-economia-tokens.md` §8 asks for and the code could not do.
*/
test('a task with no model assigned leaves the agent alone', function (): void {
    AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    expect(AiTask::modelFor('FaqDrafter'))->toBeNull();
});

test('assigning a model to a task is a row, not a deploy', function (): void {
    AiTask::create(['key' => 'FaqDrafter', 'label' => 'Redacta preguntas frecuentes']);

    expect(AiTask::modelFor('FaqDrafter'))->toBeNull();

    AiTask::query()->where('key', 'FaqDrafter')->update(['model_code' => 'modelo-barato']);
    cache()->forget('ai.tasks');

    expect(AiTask::modelFor('FaqDrafter'))->toBe('modelo-barato');
});
