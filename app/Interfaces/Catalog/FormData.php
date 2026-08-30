<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use Livewire\Wireable;

/**
 * The DTO a catalog editor carries.
 *
 * `BaseCatalogForm` keeps it in ONE `$data` property shared by the 9 masters,
 * so its type has to be what the nine have in common. It is the lock too: a
 * DTO that forgets `toPayload()` breaks when the class loads.
 */
interface FormData extends Wireable
{
    /**
     * Rebuilds the DTO from an array (the model's `toArray()` when editing).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self;

    /**
     * The DTO's state as is, for Livewire and for rebuilding it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * What goes to validation and to the Action: the same data already
     * normalised by the model — a code's case, a name's spacing, a null column.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
