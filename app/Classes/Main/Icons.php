<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * The glyphs a row may wear: the KEYS of config/icons.php, the real catalog.
 * Free text will not do: `<x-icon>` with a name that does not exist draws
 * nothing and the row silently ends up iconless.
 */
final class Icons
{
    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $icons = array_keys(config('icons'));
        sort($icons);

        return array_map(fn (string $icon): array => ['value' => $icon, 'label' => $icon], $icons);
    }
}
