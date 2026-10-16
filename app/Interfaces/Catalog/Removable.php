<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

/**
 * A catalog record the admin can delete, and what stands in the way.
 *
 * The database cannot answer "is this in use" for us: a business that was
 * removed is a soft-deleted row, and a restored one would point at a record
 * that is gone. Every master therefore says, in its own words, who holds it.
 */
interface Removable
{
    /**
     * Who keeps this record alive. Anything above zero blocks the delete.
     *
     * Keys are `catalog.usage.*` nouns, so the screen can say "14 negocios y
     * 3 países" instead of "it is in use". Soft-deleted holders count too:
     * they can come back.
     *
     * @return array<string, int>
     */
    public function usage(): array;

    /**
     * What goes away WITH it: the record's own configuration, never somebody
     * else's data. Shown in the confirmation so nothing leaves unannounced.
     *
     * @return array<string, int>
     */
    public function owns(): array;

    /** Lets go of {@see owns()} before the row goes, for configuration held by a RESTRICT key. */
    public function detachOwned(): void;
}
