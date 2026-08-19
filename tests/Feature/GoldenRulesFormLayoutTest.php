<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — master form layout
|--------------------------------------------------------------------------
| Three rules are locked in here, and all three came from a real defect:
|
|  1. A form row always reaches the right edge. No editor hand-assigns widths.
|  2. Rows are DECLARED by the author, never left to the browser's wrap — a
|     wrap-driven layout changes shape with the viewport and drops the last
|     field (usually the state toggle) alone onto a full row.
|  3. A field's message sits far closer to its own control than to the next
|     row, so an error can never read as belonging to the field below.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * The Blade source of every master editor, keyed by its component name.
 *
 * @return array<string, string>
 */
function catalogEditors(): array
{
    return collect(File::files(resource_path('views/components/catalog')))
        ->filter(fn ($file): bool => str_starts_with($file->getFilename(), '⚡'))
        ->reject(fn ($file): bool => str_contains($file->getFilename(), 'placeholder')
            || str_contains($file->getFilename(), 'manager'))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => $file->getContents()])
        ->all();
}

/** The declaration block of a CSS rule, by exact selector. */
function cssRule(string $selector): string
{
    $css = File::get(resource_path('css/app.css'));
    preg_match('/^\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $css, $match);

    return $match[1] ?? '';
}

/** The px value of a CSS `gap` declaration; the row gap when two are given. */
function gapPx(string $declarations, int $part = 0): int
{
    preg_match('/gap:\s*([^;]+);/', $declarations, $match);
    $values = preg_split('/\s+/', trim($match[1] ?? ''));

    return (int) $values[$part];
}

test('the master form never caps its width, so no dead space is left on the right', function (): void {
    // A `max-width` here was leaving ~490px unused inside a 1170px panel while
    // the fields were squeezed into a tall stack.
    expect(cssRule('.catalog-form'))->not->toContain('max-width');
});

test('a form row is a flex line, and something in it can absorb the leftover', function (): void {
    // A 3-letter code must NOT stretch — the name is what takes the space, which
    // is why `f-code` has grow 0. The invariant is therefore per ROW: a row built
    // only out of codes would stop short of the right edge.
    expect(cssRule('.catalog-form .form-row'))->toContain('display:flex');

    $css = File::get(resource_path('css/app.css'));
    preg_match_all('/\.catalog-form \.(f-\w+)\s*\{\s*flex:\s*(\d+)/', $css, $spans, PREG_SET_ORDER);

    $absorbs = collect($spans)
        ->filter(fn (array $span): bool => (int) $span[2] > 0)
        ->map(fn (array $span): string => str_replace('f-', '', $span[1]))
        ->all();

    expect($absorbs)->not->toBeEmpty();

    $offenders = [];

    foreach (catalogEditors() as $name => $blade) {
        preg_match_all('/<x-catalog\.form-row.*?<\/x-catalog\.form-row>/s', $blade, $rows);

        foreach ($rows[0] as $index => $row) {
            preg_match_all('/span="(\w+)"/', $row, $used);

            if (array_intersect($used[1], $absorbs) === []) {
                $offenders[] = "{$name} → row #{$index} has nothing that can fill it";
            }
        }
    }

    expect(implode("\n", $offenders))->toBe('');
});

test('a field message stays far closer to its own control than to the next row', function (): void {
    // When a field shows its error it grows, and the message floats between two
    // rows. If the three distances are similar the eye cannot tell which field
    // the error belongs to, so the row gap has to dominate.
    $betweenRows = gapPx(cssRule('.catalog-form'));
    $insideRow = gapPx(cssRule('.catalog-form .form-row'));
    $controlToMessage = cssRule('.field');

    preg_match('/gap:\s*(\.?[\d.]+)rem/', $controlToMessage, $match);
    $controlToMessagePx = (float) $match[1] * 16;

    expect($betweenRows)->toBeGreaterThan($insideRow)
        ->and($betweenRows)->toBeGreaterThan($controlToMessagePx * 2);
});

test('the panel that holds the editor never clips it', function (): void {
    // `overflow:hidden` on the panel used to cut the combobox dropdown against
    // the bottom of the card: with a compact form the panel is barely taller than
    // the form, so the option list was sliced in half and read as if it rendered
    // behind the form. Only the action footer's background needed containing, and
    // it now rounds its own corners.
    expect(cssRule('.catalog-panel'))->not->toMatch('/overflow(-y)?\s*:\s*(hidden|clip|auto|scroll)/');

    // Y como el panel ya no recorta, TODO fondo que toque un borde del card tiene
    // que redondear sus propias esquinas o se sale por encima del radio.
    expect(cssRule('.catalog-form-foot'))->toContain('border-bottom-left-radius')
        ->and(cssRule('.catalog-panel-head'))->toContain('border-top-left-radius');
});

test('the master header takes its colour from a brand token, never a hex', function (): void {
    // The wash is what gives the header presence, and it has to resolve per theme:
    // `--brand-soft` is jade-50 in light and a translucent jade in dark, so the
    // same declaration covers both without a line of dark-mode CSS.
    $head = cssRule('.catalog-panel-head');

    expect($head)->toContain('background:var(--brand-soft)')
        ->not->toMatch('/#[0-9a-fA-F]{3,6}/');
});

test('no catalog editor hand-assigns a width', function (): void {
    // Hand-picked column spans (`col-4` + `col-8`) were the origin of the ragged
    // edge: sooner or later a master picks spans that do not add up.
    $offenders = collect(catalogEditors())
        ->filter(fn (string $blade): bool => preg_match('/\bcol-\d+\b|style="[^"]*width|\bw-\[/', $blade) === 1)
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('every catalog editor declares its rows instead of letting the browser wrap them', function (): void {
    $offenders = collect(catalogEditors())
        ->filter(fn (string $blade): bool => ! str_contains($blade, '<x-catalog.form-row'))
        ->keys();

    expect($offenders->implode("\n"))->toBe('');
});

test('every field of a master form declares what it is, so the row can size it', function (): void {
    // Without `span` a field falls back to the descriptive width, which silently
    // gives a 3-letter code the room meant for a name.
    $offenders = [];

    foreach (catalogEditors() as $name => $blade) {
        preg_match_all('/<x-inputsform\.[\w-]+((?:[^>"]|"[^"]*")*)\/?>/', $blade, $matches);

        foreach ($matches[1] as $index => $attributes) {
            // The toolbar search box lives outside the form grid.
            if (str_contains($attributes, 'name="q"')) {
                continue;
            }

            if (! str_contains($attributes, 'span=')) {
                $offenders[] = "{$name} → field #{$index}";
            }
        }
    }

    expect(implode("\n", $offenders))->toBe('');
});

test('no catalog editor re-declares the shared chrome', function (): void {
    // The toolbar, the table, the form bar, the action footer and the Alpine
    // rail were copied into all three editors: a fix in one never reached the
    // other two.
    $duplicated = ['Alpine.data(', 'class="catalog-table"', 'catalog-formbar', 'catalog-form-foot', 'catalog-toolbar'];

    $offenders = [];

    foreach (catalogEditors() as $name => $blade) {
        foreach ($duplicated as $chrome) {
            if (str_contains($blade, $chrome)) {
                $offenders[] = "{$name} → {$chrome}";
            }
        }
    }

    expect(implode("\n", $offenders))->toBe('');
});
