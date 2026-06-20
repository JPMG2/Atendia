<?php

use Illuminate\Support\Facades\Blade;

test('the icon component renders a registered icon as inline SVG', function () {
    $html = Blade::render('<x-icon name="zap" :size="32" />');

    expect($html)
        ->toContain('<svg')
        ->toContain('width="32"')
        ->toContain('height="32"')
        ->toContain('stroke="currentColor"')
        ->toContain('class="lucide"');
});

test('the icon component renders nothing for an unknown icon', function () {
    expect(trim(Blade::render('<x-icon name="does-not-exist" />')))->toBe('');
});

test('the icon component falls back to the default size of 20', function () {
    $html = Blade::render('<x-icon name="zap" />');

    expect($html)->toContain('width="20"')->toContain('height="20"');
});

test('the icon component respects a custom size in every prop form', function (string $template, string $expected) {
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

test('two different sizes produce two different widths', function () {
    $small = Blade::render('<x-icon name="zap" :size="16" />');
    $large = Blade::render('<x-icon name="zap" :size="64" />');

    expect($small)->toContain('width="16"')->not->toContain('width="64"');
    expect($large)->toContain('width="64"')->not->toContain('width="16"');
});

test('the icon registry holds only non-empty SVG markup', function () {
    $icons = config('icons');

    expect($icons)->toBeArray()->not->toBeEmpty();

    foreach ($icons as $name => $svg) {
        expect($svg)->toBeString()
            ->and(trim($svg))->not->toBe('', "icon [{$name}] is empty");
    }
});
