<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * A registry glyph as a table row carries it for Alpine to draw, the way `<x-icon>`
 * draws it: a Lucide string is stroked, a brand logo (`['filled' => true, 'path' => …]`)
 * is filled. A key the registry no longer has draws nothing.
 */
final class RowGlyph
{
    /**
     * @return array{icon_svg: string, icon_filled: bool}
     */
    public static function fields(?string $name): array
    {
        $icon = $name === null ? null : config("icons.{$name}");
        $svg = is_array($icon) ? ($icon['path'] ?? '') : $icon;

        return [
            'icon_svg' => is_string($svg) ? $svg : '',
            'icon_filled' => is_array($icon) && ($icon['filled'] ?? false) === true,
        ];
    }
}
