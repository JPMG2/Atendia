<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use App\Dto\CatalogColumnDto;

/**
 * A master that says its own table columns, next to the rows it already gives
 * through `DataTable`. The web editor and the mobile API both read this one
 * list, so the two tables cannot drift apart.
 */
interface HasColumns
{
    /** @return list<CatalogColumnDto> */
    public function catalogColumns(): array;
}
