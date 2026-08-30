<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — no native browser alerts
|--------------------------------------------------------------------------
| AtendIa has NO alert()/confirm()/prompt(). Every notice, warning, confirmation
| and retry goes through <livewire:dialog /> via the global `dialog.*` helper.
|
| A native dialog cannot be themed, is written by the browser in the operating
| system's language, blocks the thread and is silently suppressed by some mobile
| browsers — in the client panel it reads as a system error, not as the product.
|
| There is no allowlist on purpose: nothing legitimately needs one — comments
| included. The rule's own documentation names the calls without parentheses, so
| a real one can never hide inside a comment.
|
| Guide: .ai/guidelines/avisos-y-modales.md
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

/**
 * Every file that reaches the browser, keyed by a readable path.
 *
 * @return array<string, string>
 */
function browserFacingSources(): array
{
    $sources = [];

    foreach ([resource_path('views'), resource_path('js')] as $base) {
        foreach (File::allFiles($base) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php') && $file->getExtension() !== 'js') {
                continue;
            }

            $key = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen(resource_path())), '/\\'));
            $sources[$key] = $file->getContents();
        }
    }

    return $sources;
}

/**
 * `dialog.confirm(` and `this.accept(` are not native calls: the bare pattern
 * requires the call NOT to be preceded by a dot or another identifier char.
 */
const NATIVE_ALERT_PATTERN = '/(^|[^.\w$])(alert|confirm|prompt)\s*\(|window\s*\.\s*(alert|confirm|prompt)\s*\(/i';

test('no file that reaches the browser uses a native alert, confirm or prompt', function (): void {
    $offenders = [];

    foreach (browserFacingSources() as $path => $contents) {
        if (preg_match(NATIVE_ALERT_PATTERN, $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Native browser dialogs found. Use dialog.* instead: '.implode(', ', $offenders));
});

test('the guardian would actually catch a native call', function (): void {
    // A guardian that never fires is decoration. These are the shapes it has to
    // catch, including the one hiding behind `window.`.
    foreach ([
        'if (confirm("¿Seguro?")) { doIt(); }',
        'window.confirm("¿Seguro?")',
        'alert("hola")',
        'const name = prompt("¿Cómo te llamás?");',
        'x-on:click="return confirm(\'¿Seguro?\')"',
    ] as $snippet) {
        expect(preg_match(NATIVE_ALERT_PATTERN, $snippet))->toBe(1, $snippet);
    }

    // ...and these are the shapes it must NOT flag.
    foreach ([
        "if (! await dialog.confirm({ title: 'x' })) return;",
        'this.accept()',
        'await dialog.notify({ title: "listo" })',
        '<x-ui.button x-on:click="cancel()">',
    ] as $snippet) {
        expect(preg_match(NATIVE_ALERT_PATTERN, $snippet))->toBe(0, $snippet);
    }
});

test('the system dialog is mounted once, in the dashboard layout', function (): void {
    // If it is not in the layout, `dialog.*` opens nothing and the golden rule
    // has nowhere to send its notices.
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect(substr_count($layout, '<livewire:dialog />'))->toBe(1)
        ->and($layout)->toContain('resources/js/dialog.js');
});

test('the dialog covers the four kinds of notice and themes itself through tokens', function (): void {
    $blade = file_get_contents(resource_path('views/components/⚡dialog.blade.php'));

    // One window for notices, warnings, confirmations and retries: the type only
    // changes the colour and the glyph, never the markup.
    foreach (['info', 'success', 'warning', 'danger'] as $type) {
        expect($blade)->toContain("current.type === '{$type}'");
    }

    expect($blade)
        ->toContain('role="alertdialog"')      // it interrupts to ask, and is announced whole
        ->toContain('aria-modal="true"')
        ->toContain('x-on:keydown.escape.window="cancel()"')
        ->toContain('x-on:click.self="cancel()"')     // escaping never runs the action
        ->not->toContain('#');                        // colour comes from tokens only
});

test('the dialog helper answers with a promise, so the caller reads like normal code', function (): void {
    $js = file_get_contents(resource_path('js/dialog.js'));

    expect($js)->toContain('return new Promise(')
        ->toContain('notify:')
        ->toContain('confirm:')
        ->toContain('retry:')
        // Cancelling and confirming both resolve: a caller can never hang.
        ->toContain('resolve?.(value)');
});
