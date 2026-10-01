<?php

declare(strict_types=1);

namespace App\Classes\Search;

use Illuminate\Support\HtmlString;

/**
 * Marks the piece that matched, so a row explains itself: seeing WHY a thread
 * came back is the difference between a result and a guess (the same thing
 * Slack does with its search terms).
 */
final class Highlight
{
    /**
     * The text, escaped, with every occurrence of the term wrapped. Accents are
     * folded on both sides, so typing "depilacion" still marks "depilación".
     *
     * The escaping happens FIRST and the tags are added after: the text is a
     * customer's name or a message body, and it is never trusted as markup.
     */
    public static function mark(string $text, string $term): HtmlString
    {
        $term = trim($term);

        if ($term === '' || $text === '') {
            return new HtmlString(e($text));
        }

        $haystack = Accents::fold(mb_strtolower($text));
        $needle = Accents::fold(mb_strtolower($term));
        $length = mb_strlen($needle);

        if ($length === 0 || ! str_contains($haystack, $needle)) {
            return new HtmlString(e($text));
        }

        $out = '';
        $cursor = 0;

        // Folding keeps the string length, so an offset found in the folded
        // copy points at the same character in the original.
        while (($at = mb_strpos($haystack, $needle, $cursor)) !== false) {
            $out .= e(mb_substr($text, $cursor, $at - $cursor));
            $out .= '<mark class="cmdk-mark">'.e(mb_substr($text, $at, $length)).'</mark>';
            $cursor = $at + $length;
        }

        return new HtmlString($out.e(mb_substr($text, $cursor)));
    }

    /**
     * How far a word may be from what she typed and still be the one meant.
     * Two edits covers a plural, a typo and a missing accent; past that the
     * guess stops being a guess.
     */
    private const int MAX_EDITS = 2;

    private const int MIN_WORD = 4;

    /**
     * For a row that came back by MEANING there is no literal match, so the
     * exact pass finds nothing. This marks the nearest word instead — the one
     * that explains why the row is here — and says so by whispering: a guess
     * gets a dotted underline, never the solid wash of a real match.
     */
    public static function markClosest(string $text, string $term): HtmlString
    {
        $exact = self::mark($text, $term);

        if (str_contains($exact->toHtml(), '<mark')) {
            return $exact;
        }

        $word = self::closestWord($text, $term);

        if ($word === null) {
            return new HtmlString(e($text));
        }

        $at = mb_strpos(Accents::fold(mb_strtolower($text)), Accents::fold(mb_strtolower($word)));

        if ($at === false) {
            return new HtmlString(e($text));
        }

        return new HtmlString(
            e(mb_substr($text, 0, $at))
            .'<mark class="cmdk-mark is-loose">'.e(mb_substr($text, $at, mb_strlen($word))).'</mark>'
            .e(mb_substr($text, $at + mb_strlen($word)))
        );
    }

    /** The word of the text nearest to any word she typed, or null. */
    private static function closestWord(string $text, string $term): ?string
    {
        $needles = array_filter(
            preg_split('/\s+/', Accents::fold(mb_strtolower(trim($term)))) ?: [],
            fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD,
        );

        if ($needles === []) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach (preg_split('/\s+/', $text) ?: [] as $candidate) {
            $clean = trim($candidate, " \t\n\r\0\x0B.,;:¿?¡!()\"'");

            if (mb_strlen($clean) < self::MIN_WORD) {
                continue;
            }

            foreach ($needles as $needle) {
                $distance = levenshtein(Accents::fold(mb_strtolower($clean)), $needle);

                if ($distance < $bestDistance) {
                    $best = $clean;
                    $bestDistance = $distance;
                }
            }
        }

        return $bestDistance <= self::MAX_EDITS ? $best : null;
    }
}
