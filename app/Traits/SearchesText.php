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
        $words = self::searchWords($term, $minWord);

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
     * The words of a sentence that can mean something. Split on anything that
     * is not a letter or digit, so "verificar," still matches "verificar".
     *
     * @return list<string>
     */
    private static function searchWords(string $term, int $minWord): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $word): bool => mb_strlen($word) >= $minWord
                && ! in_array(Accents::fold(mb_strtolower($word)), self::STOP_WORDS, true),
        ));
    }

    /**
     * Most distinct words of the sentence first. With a generic word like
     * "asistente" in half the titles, the article that matches two words of
     * the sentence has to beat the ones that match only the generic one.
     *
     * @param  list<string>  $columns
     */
    public function scopeOrderByWordHits(Builder $query, array $columns, string $term, int $minWord = 4): Builder
    {
        $words = self::searchWords($term, $minWord);
        $score = [];
        $bindings = [];

        foreach ($words as $word) {
            $hit = [];

            foreach ($columns as $column) {
                $hit[] = "translate(lower({$column}), ?, ?) like ?";
                array_push($bindings, Accents::ACCENTED, Accents::PLAIN, Accents::needle($word));
            }

            $score[] = 'case when '.implode(' or ', $hit).' then 1 else 0 end';
        }

        return $score === [] ? $query : $query->orderByRaw('('.implode(' + ', $score).') desc', $bindings);
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
