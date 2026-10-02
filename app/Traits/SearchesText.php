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
     * Spanish function words long enough to survive the length filter. Without
     * this "desde" in a sentence matches "…productos desde Excel" and every
     * sentence becomes a hit.
     *
     * @var list<string>
     */
    private const array STOP_WORDS = [
        'desde', 'para', 'como', 'cuando', 'donde', 'pero', 'porque', 'esta',
        'este', 'esto', 'esos', 'esas', 'todo', 'toda', 'todos', 'todas',
        'unos', 'unas', 'sobre', 'entre', 'hasta', 'cada', 'otro', 'otra',
        'muy', 'mas', 'sino', 'aunque', 'mientras', 'tambien', 'solo',
    ];

    /**
     * Any of the words, not the whole phrase. Someone describing a problem
     * writes a sentence, and a sentence matched as one substring never hits
     * anything: "mi asistente dejó de responder" has to find "no responde".
     *
     * @param  list<string>  $columns
     */
    public function scopeWhereAnyWordMatches(Builder $query, array $columns, string $term, int $minWord = 4): Builder
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($term)) ?: [],
            fn (string $word): bool => mb_strlen($word) >= $minWord
                && ! in_array(Accents::fold(mb_strtolower($word)), self::STOP_WORDS, true),
        ));

        if ($words === []) {
            return $query->whereTextMatches($columns, $term);
        }

        return $query->where(function (Builder $query) use ($columns, $words): void {
            foreach ($words as $word) {
                $query->orWhere(fn (Builder $inner) => $inner->whereTextMatches($columns, $word));
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
