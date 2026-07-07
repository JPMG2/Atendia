<?php

use Illuminate\Support\Facades\Blade;

test('the icon component renders a registered icon as inline SVG', function (): void {
    $html = Blade::render('<x-icon name="zap" :size="32" />');

    expect($html)
        ->toContain('<svg')
        ->toContain('width="32"')
        ->toContain('height="32"')
        ->toContain('stroke="currentColor"')
        ->toContain('class="lucide"');
});

test('the icon component renders nothing for an unknown icon', function (): void {
    expect(trim(Blade::render('<x-icon name="does-not-exist" />')))->toBe('');
});

test('the icon component falls back to the default size of 20', function (): void {
    $html = Blade::render('<x-icon name="zap" />');

    expect($html)->toContain('width="20"')->toContain('height="20"');
});

test('the icon component respects a custom size in every prop form', function (string $template, string $expected): void {
    $html = Blade::render($template);

    expect($html)
        ->toContain('width="'.$expected.'"')
        ->toContain('height="'.$expected.'"')
        // The custom size must win — the default 20 must NOT leak through.
        ->not->toContain('width="20"');
})->with([
    'bound int       :size=32' => ['<x-icon name="zap" :size="32" />', '32'],
    'string attribute size="14"' => ['<x-icon name="zap" size="14" />', '14'],
    'large           :size=48' => ['<x-icon name="zap" :size="48" />', '48'],
]);

test('two different sizes produce two different widths', function (): void {
    $small = Blade::render('<x-icon name="zap" :size="16" />');
    $large = Blade::render('<x-icon name="zap" :size="64" />');

    expect($small)->toContain('width="16"')->not->toContain('width="64"');
    expect($large)->toContain('width="64"')->not->toContain('width="16"');
});

test('the icon registry holds only non-empty SVG markup', function (): void {
    $icons = config('icons');

    expect($icons)->toBeArray()->not->toBeEmpty();

    foreach ($icons as $name => $icon) {
        // An icon is either a string (Lucide, stroke) or a brand entry
        // ['filled' => true, 'path' => ...] (Simple Icons, fill).
        $path = is_array($icon) ? ($icon['path'] ?? null) : $icon;

        expect($path)->toBeString("icon [{$name}] has no path")
            ->and(trim((string) $path))->not->toBe('', "icon [{$name}] is empty");
    }
});

test('the icon component renders a brand icon as a filled SVG', function (): void {
    $html = Blade::render('<x-icon name="facebook" :size="24" />');

    expect($html)
        ->toContain('<svg')
        ->toContain('fill="currentColor"')
        ->toContain('stroke="none"')
        ->toContain('class="brand-icon"')
        // The stroke styling of Lucide icons must NOT leak into a brand icon.
        ->not->toContain('fill="none"')
        // The raw array must never reach the markup.
        ->not->toContain('Array');
});

test('a brand icon keeps carrying its real path, not the word Array', function (): void {
    $html = Blade::render('<x-icon name="x-twitter" />');

    expect($html)->toContain('<path d="M14.234');
});
