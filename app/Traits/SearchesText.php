<?php

declare(strict_types=1);

namespace App\Traits;

use App\Classes\Search\Accents;
use Illuminate\Database\Eloquent\Builder;

/**
 * The words lane of the global search. Accents are folded with translate()
 * instead of unaccent(): the extension needs a superuser this app does not
 * have, and "cafe" has to find "Café" anyway.
 */
trait SearchesText
{
    /**
     * @param  list<string>  $columns
     */
    public function scopeWhereTextMatches(Builder $query, array $columns, string $term): Builder
    {
        $needle = Accents::needle($term);

        return $query->where(function (Builder $query) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $query->orWhereRaw(
                    "translate(lower({$column}), ?, ?) like ?",
                    [Accents::ACCENTED, Accents::PLAIN, $needle],
                );
            }
        });
    }

    /**
     * What the owner types is usually a name, so a hit in the leading column
     * sorts above one that only matched deep in a description.
     */
    public function scopeOrderByLeadingMatch(Builder $query, string $column, string $term): Builder
    {
        return $query->orderByRaw(
            "case when translate(lower({$column}), ?, ?) like ? then 0 else 1 end",
            [Accents::ACCENTED, Accents::PLAIN, Accents::needle($term)],
        );
    }
}
