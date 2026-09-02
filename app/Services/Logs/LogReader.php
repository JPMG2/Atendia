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
    private const TAIL_BYTES = 524_288;

    private const ENTRY_HEADER = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s?(.*)$/';

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

        // testing.log is the suite's own noise (tests error on purpose): for
        // the owner reading this screen it is never a system log.
        return array_values(array_filter(
            array_map(static fn (string $path): string => basename($path), $paths),
            static fn (string $name): bool => $name !== 'testing.log',
        ));
    }

    /**
     * The latest entries of ONE file, newest first.
     *
     * The name must be one of {@see files()}: it travels from the browser, so
     * anything else — a path, a dot-dot — reads nothing.
     *
     * @return Collection<int, LogEntryDto>
     */
    public function entries(string $file, int $limit = 100): Collection
    {
        if (! in_array($file, $this->files(), true)) {
            return collect();
        }

        return $this->parse($this->tail($this->directory().'/'.$file))
            ->reverse()
            ->values()
            ->take($limit);
    }

    private function directory(): string
    {
        return $this->directory ?? storage_path('logs');
    }

    private function tail(string $path): string
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            if ($size > self::TAIL_BYTES) {
                fseek($handle, -self::TAIL_BYTES, SEEK_END);
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
