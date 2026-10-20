<?php

declare(strict_types=1);

namespace App\Services\Logs;

use App\Dto\LogEntryDto;
use Illuminate\Support\Collection;

/**
 * Reads the system log files for the admin screen, newest first.
 *
 * Only the TAIL of a file is ever read: a log grows to megabytes and the
 * screen wants the latest entries, not a full parse that eats the request.
 */
class LogReader
{
    /** How far back from the end of a file the detail looks for the newest example of a problem. */
    private const int SAMPLE_BYTES = 2_097_152;

    /** The first line of an entry: timestamp, environment, level and the headline. */
    public const string ENTRY_HEADER = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s?(.*)$/';

    /** The directory is injectable so tests never touch the real logs. */
    public function __construct(
        private readonly ?string $directory = null,
    ) {}

    /**
     * The log files available, most recently written first.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $paths = glob($this->directory().'/*.log') ?: [];

        usort($paths, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        // testing.log and browser.log are the suite's own noise (tests error
        // on purpose): for the owner reading this screen they are never a
        // system log.
        return array_values(array_filter(
            array_map(basename(...), $paths),
            static fn (string $name): bool => ! in_array($name, ['testing.log', 'browser.log'], true),
        ));
    }

    /**
     * The full path of one of {@see files()}, or null for anything else.
     *
     * The name can travel from the browser, so a path or a dot-dot reads nothing.
     */
    public function pathOf(string $file): ?string
    {
        return in_array($file, $this->files(), true) ? $this->directory().'/'.$file : null;
    }

    /**
     * The newest entry of a file that belongs to a problem, VERBATIM — headline
     * plus trace — because that is what gets pasted into a help conversation.
     * Null when the file no longer holds one: logs rotate, the summary stays.
     */
    public function latestRaw(string $file, string $fingerprint): ?string
    {
        $path = $this->pathOf($file);

        if ($path === null) {
            return null;
        }

        $entries = $this->parse($this->tail($path, self::SAMPLE_BYTES))->reverse();

        foreach ($entries as $entry) {
            if (LogFingerprint::of($entry->level, $entry->message)?->hash === $fingerprint) {
                return $entry->raw;
            }
        }

        return null;
    }

    /**
     * What a person reading the paste needs before the trace itself.
     *
     * Pasted alone, an entry starts a round of questions — which PHP, which
     * environment, which install. This is the answer to all of them, and it
     * never guesses: only what this process really knows.
     */
    public function context(string $file = ''): string
    {
        return implode("\n", array_filter([
            '--- '.__('logs.context.title').' ---',
            __('logs.context.app').': '.config('app.name').' · '.config('app.url'),
            __('logs.context.environment').': '.app()->environment(),
            __('logs.context.versions').': PHP '.PHP_VERSION.' · Laravel '.app()->version(),
            $file === '' ? null : __('logs.context.file').': '.$file,
            __('logs.context.copied_at').': '.now()->format('d/m/Y H:i:s T'),
            '---',
        ]));
    }

    private function directory(): string
    {
        return $this->directory ?? storage_path('logs');
    }

    private function tail(string $path, int $bytes): string
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            if ($size > $bytes) {
                fseek($handle, -$bytes, SEEK_END);
            }

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Collection<int, LogEntryDto>
     */
    private function parse(string $content): Collection
    {
        $entries = collect();
        $current = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match(self::ENTRY_HEADER, $line, $header) === 1) {
                if ($current !== null) {
                    $entries->push($this->build($current));
                }

                $current = ['header' => $header, 'lines' => [$line]];

                continue;
            }

            // Lines before the first header are the half entry the tail cut
            // through: unusable without their headline, so they are dropped.
            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $entries->push($this->build($current));
        }

        return $entries;
    }

    /**
     * @param  array{header: array<int, string>, lines: list<string>}  $entry
     */
    private function build(array $entry): LogEntryDto
    {
        return new LogEntryDto(
            timestamp: $entry['header'][1],
            environment: $entry['header'][2],
            level: strtolower($entry['header'][3]),
            message: $entry['header'][4],
            raw: rtrim(implode("\n", $entry['lines'])),
        );
    }
}
