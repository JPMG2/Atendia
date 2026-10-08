<?php

declare(strict_types=1);

namespace Tests\Support\AiEval;

use App\Classes\Main\AiEvalResults;
use App\Enums\AiCapability;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Models\AiTask;
use Laravel\Ai\Contracts\Agent;

/**
 * What every AI battery shares: pointing the agents under test at a candidate
 * model, and leaving the score where the admin catalog reads it.
 */
final class EvalRun
{
    /** @var array<string, array{passed: int, total: int}> */
    private static array $tally = [];

    /**
     * Points the given tasks at the candidate, or leaves them on what they run
     * today, and says which model this run measures. Applied on every test:
     * each one rolls its database back, rows included. The candidate needs a
     * priced row only so the ladder accepts it; its price is not measured here.
     *
     * @param  list<string>  $taskKeys
     * @param  class-string<Agent>  $probe  The agent whose model names the run.
     * @return array{connection: string, model: string}
     */
    public static function target(array $taskKeys, ?string $connection, ?string $model, string $probe): array
    {
        if (filled($connection) && filled($model)) {
            AiModel::create([
                'provider' => (string) AiConnection::driverOf($connection), 'capability' => AiCapability::Vision,
                'code' => $model, 'label' => $model, 'effective_from' => '2020-01-01',
            ]);

            foreach (AiTask::query()->whereIn('key', $taskKeys)->get() as $task) {
                $task->update(['connection_key' => $connection, 'model_code' => $model]);
            }
        }

        $running = AiTask::ladderFor(class_basename($probe)) ?? $probe::declaredPair();

        return ['connection' => (string) array_key_first($running), 'model' => (string) reset($running)];
    }

    /**
     * Counts one answer and re-records the suite's score: the last write is
     * the final tally, and an interrupted run still leaves its count.
     *
     * @param  class-string<Agent>  $probe
     */
    public static function finish(string $suite, bool $pass, string $probe): void
    {
        $tally = self::$tally[$suite] ??= self::resume($suite, $probe);
        $tally['total']++;
        $tally['passed'] += $pass ? 1 : 0;
        self::$tally[$suite] = $tally;

        $target = self::target([], null, null, $probe);

        AiEvalResults::record($target['model'], $target['connection'], $tally['passed'], $tally['total'], $suite);
    }

    /**
     * Where the count starts. A long battery runs in slices (a command cannot
     * outlive its time limit, and the slices must not share the database with
     * anything else), so EVAL_CONTINUE=1 picks up the score the last slice left.
     *
     * @param  class-string<Agent>  $probe
     * @return array{passed: int, total: int}
     */
    private static function resume(string $suite, string $probe): array
    {
        $previous = getenv('EVAL_CONTINUE') === false
            ? null
            : (AiEvalResults::measured($suite)[self::target([], null, null, $probe)['model']] ?? null);

        return ['passed' => $previous->passed ?? 0, 'total' => $previous->total ?? 0];
    }
}
