<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

/**
 * A master that tells something worth seeing before its list (the next holiday of
 * each country), worked out once here so the web editor and the mobile API say it alike.
 */
interface HasHighlights
{
    /** @return list<array{when: string, text: string, mono?: bool}> `mono` false: `when` is a name, not a date, and is not set in monospace. */
    public function catalogHighlights(): array;
}
