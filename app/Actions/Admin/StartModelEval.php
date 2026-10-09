<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Classes\Main\AiEvalResults;
use App\Enums\AiCapability;
use App\Models\AiConnection;
use App\Models\AiModel;
use DomainException;
use Illuminate\Support\Facades\Process;

/**
 * Starts the mechanical battery against one catalog model and returns at once.
 *
 * The run is a separate process, not a queued job: it lasts minutes and the
 * queue would deliver it a second time while it still ran. Its own shell
 * removes the marker when it ends, so a crash cannot leave the screen "testing".
 */
class StartModelEval
{
    /**
     * @throws DomainException With the reason as its message: a key of `admin.ai.eval.errors`.
     */
    public function handle(string $code): void
    {
        $model = AiModel::query()->where('code', $code)->latest('effective_from')->first();

        if ($model === null || ! $model->capability->serves(AiCapability::Text)) {
            throw new DomainException('not_measurable');
        }

        $connection = AiConnection::firstUsableFor($model->provider);

        if ($connection === null) {
            throw new DomainException('no_key');
        }

        // Both runs reset the same test database: two at once corrupt each other.
        if (AiEvalResults::running() !== null || $this->anotherSuiteIsRunning()) {
            throw new DomainException('busy');
        }

        AiEvalResults::markRunning($code, $connection);

        $command = sprintf(
            'cd %s && EVAL_CONNECTION=%s EVAL_MODEL=%s php vendor/bin/pest tests/Eval/MechanicalEvalTest.php > %s 2>&1; rm -f %s',
            escapeshellarg(base_path()),
            escapeshellarg($connection),
            escapeshellarg($code),
            escapeshellarg(storage_path('logs/ai-eval-run.log')),
            escapeshellarg(AiEvalResults::runningPath()),
        );

        // Detached from this request: a child still attached dies with it.
        Process::run('setsid nohup sh -c '.escapeshellarg($command).' > /dev/null 2>&1 &');
    }

    /** A suite older than this is a corpse (a hung run nobody reaped), not a competitor. */
    private const int STALE_MINUTES = 60;

    private function anotherSuiteIsRunning(): bool
    {
        return collect(preg_split('/\R/', Process::run(['ps', '-o', 'etime,args'])->output()) ?: [])
            ->filter(fn (string $line): bool => str_contains($line, 'vendor/bin/pest'))
            ->contains(fn (string $line): bool => self::ageInMinutes(trim($line)) < self::STALE_MINUTES);
    }

    /**
     * The age `ps` prints in front of a line, in minutes. Both spellings exist:
     * busybox writes 22h01 and 1d01, procps writes [[dd-]hh:]mm:ss.
     */
    public static function ageInMinutes(string $line): int
    {
        $etime = strtok($line, ' ') ?: '';

        return match (true) {
            (bool) preg_match('/^(\d+)d(\d+)$/', $etime, $m) => (int) $m[1] * 1440 + (int) $m[2] * 60,
            (bool) preg_match('/^(\d+)h(\d+)$/', $etime, $m) => (int) $m[1] * 60 + (int) $m[2],
            (bool) preg_match('/^(?:(?:(\d+)-)?(\d+):)?(\d+):(\d+)$/', $etime, $m) => (int) ($m[1] ?: 0) * 1440 + (int) ($m[2] ?: 0) * 60 + (int) $m[3],
            default => 0,
        };
    }
}
