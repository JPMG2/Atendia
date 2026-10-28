<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

/**
 * A master that tells something worth seeing before its list (the next holiday of
 * each country), worked out once here so the web editor and the mobile API say it alike.
 */
interface HasHighlights
{
    /** @return list<array{when: string, text: string}> */
    public function catalogHighlights(): array;
}
