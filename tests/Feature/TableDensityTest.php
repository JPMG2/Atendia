<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Guard test for the "cell-less" data table.
 *
 * Two golden rules are locked in here:
 *  1. Every table in the panel takes its geometry from ONE density scale in
 *     app.css — no table hardcodes its own row height or cell padding.
 *  2. The table body draws no grid lines. Structure comes from column
 *     alignment and the row hover, not from a border on every cell.
 */
beforeEach(function () {
    $this->css = File::get(resource_path('css/app.css'));
});

/**
 * Every CSS rule whose selector starts with `.catalog-table`.
 *
 * @return array<int, array{selector: string, body: string}>
 */
function catalogTableRules(string $css): array
{
    preg_match_all('/^\s*(\.catalog-table[^{]*)\{([^}]*)\}/m', $css, $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $match): array => [
            'selector' => trim(preg_replace('/\s+/', ' ', $match[1])),
            'body' => $match[2],
        ],
        $matches,
    );
}

test('app.css defines the density scale once', function () {
    expect($this->css)
        ->toContain('--row-h:')
        ->toContain('--row-h-compact:')
        ->toContain('--row-px:')
        ->toContain('--table-head-h:')
        ->toContain('--table-text:')
        ->toContain('--table-head-text:')
        ->toContain('--table-leading:')
        ->toContain('--table-scroll-h:');
});

test('the table body scrolls inside the card with a pinned header', function () {
    $rules = collect(catalogTableRules($this->css));

    $wrap = $rules->firstWhere('selector', '.catalog-table-wrap');
    $head = $rules->firstWhere('selector', '.catalog-table thead th');

    expect($wrap)->not->toBeNull()
        ->and($wrap['body'])->toContain('var(--table-scroll-h)')->toContain('overflow:auto')
        ->and($head['body'])->toContain('position:sticky')->toContain('top:0')
        // Without an opaque background the rows show through the column labels
        // as they scroll under the pinned header.
        ->and($head['body'])->toContain('background:var(--surface-card)');
});

test('the pinned header draws its hairline with a shadow, not a border', function () {
    // With `border-collapse:collapse` the border of a sticky cell is left
    // behind at the top of the table and vanishes as soon as you scroll.
    $head = collect(catalogTableRules($this->css))
        ->firstWhere('selector', '.catalog-table thead th');

    expect($head['body'])
        ->toContain('box-shadow:inset')
        ->not->toMatch('/border(-(top|right|bottom|left))?\s*:/');
});

test('the code column is plain mono, not a boxed chip', function () {
    // In a table with no grid lines a bordered chip becomes the new grid: a
    // ladder of little boxes that outweighs the data it is wrapping.
    preg_match('/^\s*\.catalog-code\s*\{([^}]*)\}/m', $this->css, $match);

    expect($match)->not->toBeEmpty()
        ->and($match[1])->toContain('var(--font-mono)')
        ->not->toContain('border')
        ->not->toContain('background');
});

test('table data text never drops below 14px', function () {
    // Density comes from row height and leading, never from shrinking the
    // data font. 14px (--text-sm) is the floor set by the design system.
    expect($this->css)->toContain('--table-text:var(--text-sm)');
});

test('catalog table cells take their geometry from the density scale', function () {
    $rules = collect(catalogTableRules($this->css));

    $head = $rules->firstWhere('selector', '.catalog-table thead th');
    $body = $rules->firstWhere('selector', '.catalog-table tbody td');

    expect($head)->not->toBeNull()
        ->and($head['body'])->toContain('var(--table-head-h)')->toContain('var(--row-px)')
        ->and($body)->not->toBeNull()
        ->and($body['body'])->toContain('var(--row-h)')->toContain('var(--row-px)');
});

test('the compact variant reuses the same scale', function () {
    $compact = collect(catalogTableRules($this->css))
        ->firstWhere('selector', '.catalog-table.is-compact tbody td');

    expect($compact)->not->toBeNull()
        ->and($compact['body'])->toContain('var(--row-h-compact)');
});

test('the table body draws no grid lines', function () {
    // Only the header is allowed a border: the single hairline that separates
    // the column labels from the data. Anything else is a grid line coming back.
    $offenders = collect(catalogTableRules($this->css))
        ->reject(fn (array $rule): bool => str_contains($rule['selector'], 'thead'))
        // Only declarations that PAINT a border count: `border-collapse` is
        // layout, not ink, and the base rule legitimately sets it.
        ->filter(fn (array $rule): bool => preg_match('/border(-(top|right|bottom|left))?\s*:/', $rule['body']) === 1)
        ->pluck('selector')
        ->all();

    expect($offenders)->toBe([]);
});

test('the empty state is not squeezed into a data row', function () {
    // `.catalog-table tbody td` outranks a bare `.catalog-table-empty`, so the
    // empty message has to be scoped just as deep or it inherits the row height.
    $empty = collect(catalogTableRules($this->css))
        ->firstWhere('selector', '.catalog-table tbody td.catalog-table-empty');

    expect($empty)->not->toBeNull()
        ->and($empty['body'])->toContain('height:auto');
});

test('the boxed mono chip lives on its own class', function () {
    // `.catalog-code` and `.code-chip` look alike but answer to opposite rules:
    // a table code must stay unboxed, a log line wants its box. Any view that
    // borrows the table class inherits a restyle it never asked for.
    expect($this->css)->toContain('.code-chip {');

    // Scoped by DIRECTORY, not by filename: `components/catalog/` IS the catalog
    // table, so every editor living there is a legitimate owner of the class.
    // Enumerating the editors one by one made this guard go red on every new
    // master for doing exactly what the pattern asks.
    $borrowers = collect(File::allFiles(resource_path('views')))
        ->map(fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->reject(fn (string $path): bool => str_starts_with($path, 'components/catalog/'))
        ->filter(fn (string $path): bool => str_contains(File::get(resource_path('views/'.$path)), 'catalog-code'))
        ->values()
        ->all();

    expect($borrowers)->toBe([]);
});

test('every catalog editor renders through the shared table', function (string $editor) {
    $blade = File::get(resource_path("views/components/catalog/⚡{$editor}.blade.php"));

    expect($blade)->toContain('class="catalog-table"');
})->with(['currency', 'country', 'social-network']);
