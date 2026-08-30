<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Casts shared by the catalog DTOs.
 *
 * Every DTO used to carry its own private copy of these two, down to the
 * comment explaining the same trap. Here they live once.
 */
final class DtoCast
{
    /**
     * A foreign key arriving from the front.
     *
     * The combobox sends the id as a string and DTOs run under `strict_types`:
     * passing it to a `?int` is a TypeError that kills the component (419, blank
     * editor). "Nothing picked" arrives as '' and means null, not 0 — a 0 would
     * reach `exists` as a missing id.
     */
    public static function toNullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Text for a nullable column.
     *
     * An empty input arrives as '' and the column takes null, so null is stored.
     * Otherwise half the table holds values that are present but empty, which
     * `whereNull` never finds.
     */
    public static function toNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
