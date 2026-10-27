<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Classes\Report\CatalogReport;
use App\Dto\CatalogColumnDto;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Models\CatalogForm;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One master of the catalogs as the web's table prints it, with its words: the columns and the search
 * keys come from the model, the search is the editor's own (trimmed, any case, contained), and the
 * copy is the same `catalog.<master>.*` the editor reads. `limit` cuts what is handed over, never the count.
 */
class CatalogTable
{
    public const int PAGE = 25;

    public function __construct(
        public readonly string $slug,
        private readonly ?User $user,
        private readonly string $search = '',
        private readonly int $limit = self::PAGE,
    ) {}

    /** The master's row of the hub when the phone can read it and this person may open it; null otherwise. */
    public ?CatalogForm $form {
        get => $this->form ??= (new CatalogHub($this->user))->forms->first(fn (CatalogForm $form): bool => CatalogHub::slugOf($form) === $this->slug);
    }

    /** @var list<CatalogColumnDto> */
    public array $columns {
        get => $this->columns ??= $this->model()->catalogColumns();
    }

    /** @var Collection<int, array<string, mixed>> every row the search keeps */
    public Collection $matching {
        get => $this->matching ??= $this->searched();
    }

    public int $total {
        get => $this->matching->count();
    }

    public bool $hasMore {
        get => $this->total > $this->limit;
    }

    /**
     * Only what a column draws, and the id: the rest of a row is the editor's business.
     *
     * @return list<array<string, mixed>>
     */
    public array $rows {
        get {
            $keys = ['id', ...array_map(fn (CatalogColumnDto $column): string => $column->key, $this->columns)];

            return $this->matching
                ->take($this->limit)
                ->map(fn (array $row): array => array_intersect_key($row, array_flip($keys)))
                ->values()
                ->all();
        }
    }

    /** "provincia" or "provincias": the counter agrees in number, as the web's does. */
    public string $unit {
        get => $this->words($this->total === 1 ? 'singular' : 'plural');
    }

    public string $searchLabel {
        get => $this->words('search_label');
    }

    public string $searchPlaceholder {
        get => $this->words('search_placeholder');
    }

    public string $empty {
        get => $this->words('empty');
    }

    private function words(string $key): string
    {
        return __('catalog.'.str_replace('-', '_', $this->slug).'.'.$key);
    }

    private function model(): HasColumns&DataTable
    {
        $model = CatalogReport::MASTERS[$this->slug];

        return new $model;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function searched(): Collection
    {
        $needle = mb_strtolower(trim($this->search));
        $keys = CatalogColumnDto::searchKeys($this->columns);
        $rows = $this->model()->catalogRows();

        return $needle === ''
            ? $rows
            : $rows->filter(fn (array $row): bool => collect($keys)->contains(
                fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), $needle),
            ))->values();
    }
}
