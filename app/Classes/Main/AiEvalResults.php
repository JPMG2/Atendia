<?php

declare(strict_types=1);

namespace App\Classes\Main;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * What the AI battery measured for each model, as the admin screen reads it.
 *
 * The battery spends real tokens and builds fixture businesses, so it never
 * runs from a button: it runs from the console, and this is the one place it
 * leaves its result. A file per model and not a table, because a result that
 * lives in the database it was measured against would be wiped with it.
 */
final class AiEvalResults
{
    /** What a customer reads, and the work nobody reads: two batteries, each its own score. */
    public const string CONVERSATION = 'conversation';

    public const string MECHANICAL = 'mechanical';

    /** Keeps the last result of a model in a suite; the previous one is replaced, not appended. */
    public static function record(string $code, string $connection, int $passed, int $total, string $suite = self::CONVERSATION): void
    {
        File::ensureDirectoryExists(self::directory());

        File::put(self::path($code, $suite), json_encode([
            'code' => $code,
            'suite' => $suite,
            'connection' => $connection,
            'passed' => $passed,
            'total' => $total,
            // The wall clock, never `now()`: the battery freezes Carbon at a fixed day.
            'ran_at' => date(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Every model measured in a suite, keyed by its code.
     *
     * @return array<string, object{code: string, connection: string, passed: int, total: int, ranAt: CarbonImmutable}>
     */
    public static function measured(string $suite = self::CONVERSATION): array
    {
        return collect(File::glob(self::directory().'/*.json'))
            ->map(fn (string $file): ?array => json_decode((string) File::get($file), true))
            ->filter(fn (?array $data): bool => is_array($data) && isset($data['code'], $data['passed'], $data['total'], $data['ran_at'])
                && ($data['suite'] ?? self::CONVERSATION) === $suite)
            ->mapWithKeys(fn (array $data): array => [(string) $data['code'] => (object) [
                'code' => (string) $data['code'],
                'connection' => (string) ($data['connection'] ?? ''),
                'passed' => (int) $data['passed'],
                'total' => (int) $data['total'],
                'ranAt' => CarbonImmutable::parse($data['ran_at']),
            ]])
            ->all();
    }

    /** A run that outlives this is a run that died: the marker stops counting. */
    private const int RUN_MINUTES = 20;

    /** Says a run has started, so the screen can show it and a second one is refused. */
    public static function markRunning(string $code, string $connection): void
    {
        File::ensureDirectoryExists(self::directory());

        File::put(self::runningPath(), json_encode(['code' => $code, 'connection' => $connection, 'since' => date(DATE_ATOM)], JSON_THROW_ON_ERROR));
    }

    /**
     * The run in progress, if any.
     *
     * @return object{code: string, connection: string, since: CarbonImmutable}|null
     */
    public static function running(): ?object
    {
        $data = File::exists(self::runningPath()) ? json_decode((string) File::get(self::runningPath()), true) : null;

        if (! is_array($data) || ! isset($data['code'], $data['since'])) {
            return null;
        }

        $since = CarbonImmutable::parse($data['since']);

        return $since->addMinutes(self::RUN_MINUTES)->isPast() ? null : (object) [
            'code' => (string) $data['code'],
            'connection' => (string) ($data['connection'] ?? ''),
            'since' => $since,
        ];
    }

    /** The path the run's own shell removes when it ends, however it ended. */
    public static function runningPath(): string
    {
        return self::directory().'/running.marker';
    }

    private static function directory(): string
    {
        return storage_path('app/ai-eval');
    }

    /** A model code is typed by a person: only what is safe in a file name survives. */
    private static function path(string $code, string $suite): string
    {
        return self::directory().'/'.preg_replace('/[^A-Za-z0-9._-]/', '_', $code).'.'.$suite.'.json';
    }
}
