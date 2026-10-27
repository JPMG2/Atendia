<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\CatalogCell;

/**
 * One column of a master's table: which row key it reads, what it is called and
 * how its cell is drawn. `fill` marks the column that takes the free width, and
 * `search` the ones the master's search box looks through (each master decides).
 * `on` and `off` are the two words of an active or flag cell.
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
    ) {}

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
     * @return array{key: string, label: string, cell: string, on: string|null, off: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'cell' => $this->cell->value,
            'on' => $this->on,
            'off' => $this->off,
        ];
    }
}
