<?php

declare(strict_types=1);

namespace App\Classes\Main;

use Carbon\CarbonImmutable;

/**
 * The version the customers see and what each one brought. One source:
 * config/releases.php (numbers, dates, kinds) joined with its words in
 * lang/es/releases.php, so the footer, the panels and «Novedades» agree.
 */
class Releases
{
    /** @var list<array{version: string, date: CarbonImmutable, kind: string, kind_label: string, title: string, items: list<string>}>|null */
    private ?array $read = null;

    /** @var string The version running today: the newest row. */
    public string $current {
        get => $this->entries[0]['version'];
    }

    /** @var list<array{version: string, date: CarbonImmutable, kind: string, kind_label: string, title: string, items: list<string>}> */
    public array $entries {
        get => $this->read ??= $this->joinWords();
    }

    /**
     * The words are looked up by number from the whole list: a dotted key
     * like "releases.entries.1.0.0" would be read as three nested levels.
     *
     * @return list<array{version: string, date: CarbonImmutable, kind: string, kind_label: string, title: string, items: list<string>}>
     */
    private function joinWords(): array
    {
        $words = (array) __('releases.entries');

        return array_map(fn (array $release): array => [
            'version' => $release['version'],
            'date' => CarbonImmutable::parse($release['date']),
            'kind' => $release['kind'],
            'kind_label' => __('releases.kinds.'.$release['kind']),
            'title' => (string) ($words[$release['version']]['title'] ?? ''),
            'items' => array_values((array) ($words[$release['version']]['items'] ?? [])),
        ], config('releases'));
    }
}
