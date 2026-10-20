<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\LogProblemState;
use App\Models\LogProblem;
use App\Models\LogProblemHour;
use App\Models\PlatformFigure;
use App\Services\Logs\LogFingerprint;
use App\Services\Logs\LogReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reads what the logs gained since the last run and folds it into problems.
 *
 * Only the new bytes are read: a cursor per file lives in a platform figure,
 * which also tells the screen how fresh the reading is. The summary is kept,
 * never the trace; the trace stays in the file and is read on demand.
 */
final class IngestLogProblems
{
    public const string FIGURE = 'log_ingest';

    /** Minutes without a reading before the screen warns that what it shows may be old. */
    public const int STALE_AFTER_MINUTES = 10;

    /** Only the Laravel log channel: the other .log files in the folder are tool output. */
    private const string FILES = '/^laravel.*\.log$/';

    private const int CHUNK = 4_194_304;

    private const int MAX_CHUNKS = 25;

    public function __construct(private readonly LogReader $reader) {}

    public function handle(): PlatformFigure
    {
        /** @var array<string, array{offset: int}> $previous */
        $previous = PlatformFigure::read(self::FIGURE)?->payload['cursors'] ?? [];
        $cursors = [];
        $groups = [];
        $lines = 0;

        foreach ($this->reader->files() as $file) {
            $path = $this->reader->pathOf($file);

            if ($path === null || preg_match(self::FILES, $file) !== 1) {
                continue;
            }

            // A file that shrank was rotated or emptied: its new content starts at zero.
            $offset = (int) ($previous[$file]['offset'] ?? 0);
            $offset = $offset > (int) filesize($path) ? 0 : $offset;

            foreach ($this->newEntries($path, $offset) as $entry) {
                $lines++;
                $this->collect($groups, $file, $entry);
            }

            $cursors[$file] = ['offset' => $offset];
        }

        DB::transaction(function () use ($groups): void {
            foreach ($groups as $group) {
                $this->store($group);
            }
        });

        $this->prune();

        return PlatformFigure::put(self::FIGURE, [
            'cursors' => $cursors,
            'entries' => $lines,
            'problems' => count($groups),
        ]);
    }

    /**
     * The entry headers written after `$offset`, as timestamp, level and headline.
     * `$offset` is moved to just past the last whole line read: a half-written
     * last line is left for the next run.
     *
     * @return \Generator<int, array{0: string, 1: string, 2: string}>
     */
    private function newEntries(string $path, int &$offset): \Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            for ($chunk = 0; $chunk < self::MAX_CHUNKS; $chunk++) {
                fseek($handle, $offset);
                $buffer = (string) fread($handle, self::CHUNK);

                if ($buffer === '') {
                    return;
                }

                $end = strrpos($buffer, "\n");

                if ($end === false) {
                    // A line longer than the chunk can never finish: skip it instead of stalling on it.
                    if (strlen($buffer) < self::CHUNK) {
                        return;
                    }

                    $offset += strlen($buffer);

                    continue;
                }

                foreach (explode("\n", substr($buffer, 0, $end)) as $line) {
                    if (preg_match(LogReader::ENTRY_HEADER, $line, $header) === 1) {
                        yield [$header[1], $header[3], $header[4]];
                    }
                }

                $offset += $end + 1;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, array{fingerprint: LogFingerprint, first: CarbonImmutable, last: CarbonImmutable, file: string, hours: array<string, int>}>  $groups
     * @param  array{0: string, 1: string, 2: string}  $entry  Timestamp, level, headline
     */
    private function collect(array &$groups, string $file, array $entry): void
    {
        $fingerprint = LogFingerprint::of($entry[1], $entry[2]);

        if ($fingerprint === null) {
            return;
        }

        $at = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $entry[0], config('app.timezone'));
        $hour = $at->startOfHour()->toDateTimeString();
        $group = $groups[$fingerprint->hash] ?? ['fingerprint' => $fingerprint, 'first' => $at, 'last' => $at, 'file' => $file, 'hours' => []];

        $group['first'] = $at->lt($group['first']) ? $at : $group['first'];

        if ($at->gte($group['last'])) {
            $group['last'] = $at;
            $group['file'] = $file;
        }

        $group['hours'][$hour] = ($group['hours'][$hour] ?? 0) + 1;
        $groups[$fingerprint->hash] = $group;
    }

    /**
     * @param  array{fingerprint: LogFingerprint, first: CarbonImmutable, last: CarbonImmutable, file: string, hours: array<string, int>}  $group
     */
    private function store(array $group): void
    {
        $fingerprint = $group['fingerprint'];

        $problem = LogProblem::query()->where('fingerprint', $fingerprint->hash)->first() ?? new LogProblem([
            'fingerprint' => $fingerprint->hash,
            'level' => $fingerprint->level,
            'title' => $fingerprint->title,
            'exception' => $fingerprint->exception,
            'location' => $fingerprint->location,
            'first_seen_at' => $group['first'],
            'last_seen_at' => $group['last'],
            'last_file' => $group['file'],
            'state' => LogProblemState::Active,
        ]);

        if ($problem->exists) {
            $problem->first_seen_at = $group['first']->lt($problem->first_seen_at) ? $group['first'] : $problem->first_seen_at;

            if ($group['last']->gte($problem->last_seen_at)) {
                $problem->last_seen_at = $group['last'];
                $problem->last_file = $group['file'];
            }

            // Resolved, and it happened again after that: it is back, and says so.
            if ($problem->state === LogProblemState::Resolved && $problem->state_changed_at !== null && $group['last']->gt($problem->state_changed_at)) {
                $problem->state = LogProblemState::Active;
                $problem->regressed_at = $group['last'];
            }
        }

        $problem->save();

        LogProblemHour::upsert(
            array_map(
                fn (string $hour, int $count): array => ['log_problem_id' => $problem->id, 'hour' => $hour, 'occurrences' => $count],
                array_keys($group['hours']),
                array_values($group['hours']),
            ),
            ['log_problem_id', 'hour'],
            ['occurrences' => DB::raw('log_problem_hours.occurrences + excluded.occurrences')],
        );
    }

    /** History past the retention goes, and so does an untouched problem nobody has seen in that long. */
    private function prune(): void
    {
        $limit = now()->subDays(LogProblem::RETENTION_DAYS);

        LogProblemHour::query()->where('hour', '<', $limit->startOfHour())->delete();
        LogProblem::query()->where('state', LogProblemState::Active)->where('last_seen_at', '<', $limit)->delete();
    }
}
