<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\CatalogCell;
use Collator;

/**
 * One column of a master's table: which row key it reads, its name and how its cell
 * is drawn. `fill` takes the free width, `search` is looked through by the search box,
 * `on`/`off` are the words of an active or flag cell, `badges` the word and tone of each
 * value of a badge cell, and `chips` makes its values the one-tap filter over the list.
 */
final readonly class CatalogColumnDto
{
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
        return array_values(array_map(
            fn (self $column): string => $column->key,
            array_filter($columns, fn (self $column): bool => $column->search),
        ));
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
     * @return array{key: string, label: string, cell: string, on: string|null, off: string|null, badges: array<string, array{label: string, tone: string}>|null}
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
        ];
    }
}
