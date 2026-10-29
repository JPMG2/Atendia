<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\CatalogCell;
use Collator;

/**
 * One column of a master's table: which row key it reads, its name and how its cell is
 * drawn. `fill` takes the free width; `search` and `searchAlso` are what the box looks
 * through; `on`/`off`, `badges` and `chips` serve active, badge and filter columns; a name
 * may bring other row keys along: a line under it (`sub`), a brand tag (`tag`), a glyph (`icon`),
 * a code under it (`codeBelow`). `copy` names the copy button; `muted` names the row key that greys a cell out.
 */
final readonly class CatalogColumnDto
{
    /** More values than this and the chips become one country selector, on the web and on the phone alike. */
    public const int CHIPS_MAX = 6;

    public function __construct(
        public string $key,
        public string $label,
        public CatalogCell $cell,
        public bool $fill = false,
        public ?string $on = null,
        public ?string $off = null,
        public bool $search = false,
        /** @var array<string, array{label: string, tone: string}> */
        public array $badges = [],
        public bool $chips = false,
        public ?string $sub = null,
        public ?string $tag = null,
        public ?string $icon = null,
        /** @var list<string> */
        public array $searchAlso = [],
        /** @var array{label: string, done: string}|null */
        public ?array $copy = null,
        public ?string $codeBelow = null,
        public ?string $muted = null,
        /** @var array{key: string, label: string}|null The row key holding an address the cell opens, and the button's words. */
        public ?array $link = null,
        /** A line under a name in the body's small type (the row key), and the row key that turns it amber. */
        public ?string $note = null,
        public ?string $noteWarning = null,
        /** A control only the web draws (a switch, a button): the read-only phone never receives the column. */
        public bool $webOnly = false,
        /** An on/off pill whose off word depends on the row (why it is off): the row key holding that word. */
        public ?string $offFrom = null,
    ) {}

    /**
     * The row key the chips filter by, or null when the master offers none.
     *
     * @param  list<self>  $columns
     */
    public static function chipKey(array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($column->chips) {
                return $column->key;
            }
        }

        return null;
    }

    /**
     * The chips of a list: each distinct value once, alphabetical as Spanish reads it
     * (ICU rules: accents ignored, the tilde n after n). The browser's `Intl.Collator('es')`
     * sorts the same way, so the web and the phone show one order.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function chipValues(iterable $rows, string $key): array
    {
        $values = collect($rows)->pluck($key)->map(fn (mixed $value): string => (string) $value)->filter()->unique()->values()->all();

        return self::spanishOrder($values);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function spanishOrder(array $values): array
    {
        $collator = new Collator('es');
        usort($values, fn (string $a, string $b): int => $collator->compare($a, $b));

        return $values;
    }

    /**
     * The row keys a search box looks through, as each master declared them.
     *
     * @param  list<self>  $columns
     * @return list<string>
     */
    public static function searchKeys(array $columns): array
    {
        return collect($columns)
            ->filter(fn (self $column): bool => $column->search)
            ->flatMap(fn (self $column): array => [$column->key, ...$column->searchAlso])
            ->values()
            ->all();
    }

    /**
     * The shape `<x-catalog.table>` takes for its header: the wide column and a number's alignment.
     *
     * @return array{label: string, class?: string}
     */
    public function toTableColumn(): array
    {
        $class = trim(($this->fill ? 'catalog-col-fill ' : '').($this->cell === CatalogCell::Number ? 'is-num' : ''));

        return $class === '' ? ['label' => $this->label] : ['label' => $this->label, 'class' => $class];
    }

    /**
     * What the mobile API sends for this column.
     *
     * @return array{key: string, label: string, cell: string, on: string|null, off: string|null, badges: array<string, array{label: string, tone: string}>|null, sub: string|null, tag: string|null, icon: string|null, copy: array{label: string, done: string}|null, codeBelow: string|null, muted: string|null, link: array{key: string, label: string}|null, note: string|null, noteWarning: string|null, offFrom: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'cell' => $this->cell->value,
            'on' => $this->on,
            'off' => $this->off,
            'badges' => $this->badges === [] ? null : $this->badges,
            'sub' => $this->sub,
            'tag' => $this->tag,
            'icon' => $this->icon,
            'copy' => $this->copy,
            'codeBelow' => $this->codeBelow,
            'muted' => $this->muted,
            'link' => $this->link,
            'note' => $this->note,
            'noteWarning' => $this->noteWarning,
            'offFrom' => $this->offFrom,
        ];
    }
}
