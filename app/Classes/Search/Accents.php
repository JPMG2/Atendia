<?php

declare(strict_types=1);

namespace App\Classes\Search;

/**
 * Folding accents the same way in PHP and in SQL. unaccent() would need a
 * Postgres superuser this app does not have, so translate() does the job and
 * these two strings are the pairing both sides share.
 */
final class Accents
{
    public const string ACCENTED = 'áéíóúüñÁÉÍÓÚÜÑ';

    public const string PLAIN = 'aeiouunAEIOUUN';

    public static function fold(string $value): string
    {
        return strtr($value, array_combine(
            mb_str_split(self::ACCENTED),
            mb_str_split(self::PLAIN),
        ));
    }

    /** Lowercased, folded and wrapped for a LIKE. */
    public static function needle(string $term): string
    {
        return '%'.self::fold(mb_strtolower(trim($term))).'%';
    }
}
