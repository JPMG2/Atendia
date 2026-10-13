<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * Finds the money figures in a reply that nothing the assistant was given
 * backs up: not a tool result, not the customer's own words, not a turn
 * already in the thread. Only figures tagged as money are judged, so an hour
 * or a quantity never trips it; a sum, a difference, a product or a percent
 * of two known figures counts as known (a total is not an invention).
 */
final class GroundedFigures
{
    private const NUMBER = '\d[\d.,]*\d|\d';

    /** Distinct known figures past this stop combining: a long tool dump would validate anything. */
    private const COMBINE_LIMIT = 60;

    /**
     * @param  iterable<string>  $sources
     * @return list<string> the figures as the reply wrote them
     */
    public static function missing(string $reply, iterable $sources): array
    {
        $claimed = self::claimed($reply);

        if ($claimed === []) {
            return [];
        }

        $known = [];

        foreach ($sources as $source) {
            foreach (self::numbers($source) as $value) {
                $known[(string) round($value, 2)] = $value;
            }
        }

        $known = array_values($known);

        return collect($claimed)
            ->reject(fn (float $value): bool => self::isKnown($value, $known))
            ->keys()
            ->map(fn (int|string $written): string => (string) $written)
            ->values()
            ->all();
    }

    /**
     * @return array<string, float> written figure => value
     */
    private static function claimed(string $reply): array
    {
        $number = self::NUMBER;
        $before = '(?:us\$|u\$s|usd|ars|eur|ves|usdt|bs\.?|\$|€)';
        $after = '(?:us\$|usd|ars|eur|usdt|d[oó]lar(?:es)?|pesos?|bol[ií]vares|euros?|bs\b|\$|€)';
        $found = [];

        foreach (["/{$before}\\s*({$number})/iu", "/({$number})\\s*{$after}/iu"] as $pattern) {
            preg_match_all($pattern, $reply, $matches);

            foreach ($matches[1] as $written) {
                $found[$written] = self::parse($written);
            }
        }

        return $found;
    }

    /** @return list<float> */
    private static function numbers(string $text): array
    {
        preg_match_all('/'.self::NUMBER.'/', $text, $matches);

        return array_map(self::parse(...), $matches[0]);
    }

    /** "1.250,50", "1,250.50", "12.5" and "1.250" each read as the figure a person meant. */
    private static function parse(string $token): float
    {
        $last = max((int) strrpos($token, '.'), (int) strrpos($token, ','));

        if ($last === 0) {
            return (float) $token;
        }

        if (strlen($token) - $last - 1 === 3) {
            return (float) str_replace(['.', ','], '', $token);
        }

        $integer = str_replace(['.', ','], '', substr($token, 0, $last));

        return (float) ($integer.'.'.substr($token, $last + 1));
    }

    /** @param  list<float>  $known */
    private static function isKnown(float $value, array $known): bool
    {
        foreach ($known as $figure) {
            if (self::same($value, $figure)) {
                return true;
            }
        }

        if (count($known) > self::COMBINE_LIMIT) {
            return false;
        }

        foreach ($known as $a) {
            foreach ($known as $b) {
                $derived = [$a + $b, abs($a - $b), $a * $b, $a * (1 - $b / 100), $a * (1 + $b / 100)];

                foreach ($derived as $candidate) {
                    if (self::same($value, $candidate)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function same(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }
}
