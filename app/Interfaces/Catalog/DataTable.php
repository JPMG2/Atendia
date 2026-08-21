<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use Illuminate\Support\Collection;

interface DataTable
{
    public function catalogRows(): Collection;
}
