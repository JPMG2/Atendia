<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\CatalogCell;

/**
 * One column of a master's table: which row key it reads, what it is called and
 * how its cell is drawn. `fill` marks the column that takes the free width.
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
    ) {}

    /**
     * The row keys a search box looks through: the words on screen (a name or a
     * text), never a tag or an on/off switch.
     *
     * @param  list<self>  $columns
     * @return list<string>
     */
    public static function searchKeys(array $columns): array
    {
        return array_values(array_map(
            fn (self $column): string => $column->key,
            array_filter($columns, fn (self $column): bool => in_array($column->cell, [CatalogCell::Name, CatalogCell::Text], true)),
        ));
    }

    /**
     * The shape `<x-catalog.table>` takes for its header.
     *
     * @return array{label: string, class?: string}
     */
    public function toTableColumn(): array
    {
        return $this->fill
            ? ['label' => $this->label, 'class' => 'catalog-col-fill']
            : ['label' => $this->label];
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
