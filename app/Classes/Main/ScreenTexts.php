<?php

declare(strict_types=1);

namespace App\Classes\Main;

use Illuminate\Support\Arr;

/**
 * What a screen prints, read from the SAME translations the screen uses: a
 * renamed field or a new hint reaches the assistants with no second copy to
 * keep in sync, and they can never describe a form that does not exist.
 */
class ScreenTexts
{
    /**
     * The group (or dotted path inside one) as `key: text` lines. A regional
     * file (es_AR) is partial, so the base language is laid underneath first.
     */
    public static function of(string $group): string
    {
        $screen = array_replace_recursive(
            (array) trans($group, [], (string) config('app.fallback_locale')),
            (array) trans($group),
        );

        return collect(Arr::dot($screen))
            ->filter(fn (mixed $text): bool => is_string($text) && $text !== '')
            ->map(fn (string $text, string $key): string => $key.': '.strip_tags($text))
            ->implode("\n");
    }
}
