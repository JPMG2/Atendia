<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Services\Knowledge\KnowledgeEmbedder;
use Carbon\CarbonImmutable;
use Database\Seeders\AiModelSeeder;
use Database\Seeders\AssistantSkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiEval\CachedEmbedder;
use Tests\Support\AiEval\EvalBusinesses;
use Tests\Support\AiEval\EvalRun;
use Tests\Support\AiEval\StressCases;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The stress battery: does the assistant keep the fact under heavy load?
|--------------------------------------------------------------------------
| Six cases, two runs each (the model's weak spot was flickering, not
| failing). Every check is objective and nothing is judged by a model, so a
| run costs cents. A hard ceiling (StressCases::CAP_USD) stops the run.
|
| On demand (it spends tokens):
| EVAL_CONNECTION=openai-app EVAL_MODEL=gpt-6-luna ./vendor/bin/pest tests/Eval/StressEvalTest.php
| The per-case report lands in storage/app/logs/ai-stress.md.
*/

beforeEach(function (): void {
    Http::allowStrayRequests(['https://*']);

    $this->app->instance(KnowledgeEmbedder::class, new CachedEmbedder);
    $this->seed(AssistantSkillSeeder::class);
    $this->seed(AiModelSeeder::class);
    $this->travelTo(CarbonImmutable::parse(EvalBusinesses::NOW_UTC, 'UTC'));

    EvalRun::target(['AsistenteAtendia'], env('EVAL_CONNECTION'), env('EVAL_MODEL'), AsistenteAtendia::class);
});

test('the assistant keeps the fact under heavy information', function (string $case): void {
    if (StressCases::overCap()) {
        $this->markTestSkipped('The US$'.StressCases::CAP_USD.' ceiling was reached.');
    }

    $model = (string) env('EVAL_MODEL', 'gpt-6-luna');
    [$business, $conversation, $question] = StressCases::seed($case);

    $response = (new AsistenteAtendia($business, $conversation))->answer($question, StressCases::attachments($case));
    $total = StressCases::charge($response, $model);
    [$passed, $why] = StressCases::verdict($case, $response->text);

    StressCases::report(sprintf(
        "## %s · %s\n- input tokens: %d · output: %d · running total: US$%.4f\n- tools: %s\n- answer: %s\n- %s\n",
        $passed ? 'PASS' : 'FAIL',
        $case,
        $response->usage->inputTokens,
        $response->usage->outputTokens,
        $total,
        $response->toolCalls->pluck('name')->implode(', ') ?: 'none',
        str_replace("\n", ' ', $response->text),
        $passed ? 'ok' : $why,
    ));

    expect($passed)->toBeTrue("{$case}: {$why}\nAnswer: {$response->text}");
})->with(array_merge(StressCases::CASES, StressCases::CASES));

afterAll(function (): void {
    StressCases::report(sprintf('Total spent: US$%.4f (ceiling US$%.2f)', StressCases::spent(), StressCases::CAP_USD));
});
