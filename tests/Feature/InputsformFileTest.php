<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| <x-inputsform.file> — the drop zone of the field library
|--------------------------------------------------------------------------
| The primitive that lets a form take a file. Like every other control of the
| library it wires its own error, declares its width by content and paints
| from tokens.
|
| Rule: .ai/guidelines/formularios.md
*/

/** Renders the component the way a screen would use it. */
function renderFileField(string $attributes = ''): string
{
    return Blade::render('<x-inputsform.file name="logo" :label="\'Logo\'" '.$attributes.' />');
}

test('the field renders its label wired to the picker', function (): void {
    $html = renderFileField();

    expect($html)->toContain('for="if-logo"')
        ->toContain('id="if-logo"')
        ->toContain('type="file"');
});

test('the zone hands the click to the picker instead of being a label of its own', function (): void {
    $html = renderFileField();

    // A second <label> would read the field's name twice to a screen reader.
    expect($html)->toContain('x-on:click="$refs.picker.click()"')
        ->toContain('role="button"')
        ->toContain('x-on:click.stop');
});

test('a dropped file is taken by the same zone', function (): void {
    expect(renderFileField())->toContain('x-on:drop.prevent="drop($event)"')
        ->toContain('x-on:dragover.prevent="dragging = true"');
});

test('the stored file is what the zone shows until another one is picked', function (): void {
    $html = renderFileField(':preview="\'https://cdn.test/logo.svg\'"');

    expect($html)->toContain('https:\/\/cdn.test\/logo.svg')
        ->toContain('x-bind:src="preview"');
});

test('the field accepts the formats a logo comes in, and no more', function (): void {
    expect(renderFileField())->toContain('accept="image/png,image/webp,image/jpeg,image/svg+xml"');
});

test('the error of the ErrorBag paints the zone on its own', function (): void {
    $html = Blade::render(
        '<x-inputsform.file name="logo" :label="\'Logo\'" :error="\'El archivo pesa demasiado.\'" />'
    );

    expect($html)->toContain('field-drop field-error')
        ->toContain('El archivo pesa demasiado.')
        ->toContain('aria-invalid="true"');
});

test('the width is declared by content, never in columns', function (string $span, string $class): void {
    expect(renderFileField('span="'.$span.'"'))->toContain('field '.$class);
})->with([
    ['code', 'f-code'],
    ['long', 'f-long'],
    ['full', 'f-full'],
    // An unknown span falls back instead of building a broken class.
    ['nonsense', 'f-text'],
]);

test('the progress bar only exists while the file is travelling', function (): void {
    expect(renderFileField())->toContain('x-show="uploading"')
        ->toContain('x-on:livewire-upload-progress="progress = $event.detail.progress"');
});

test('the zone paints from tokens, with no hex of its own', function (): void {
    expect(renderFileField())->not->toMatch('/#[0-9a-fA-F]{3}\b/');
});

test('the remove button only exists when a screen asks for it', function (): void {
    expect(renderFileField())->not->toContain('field-drop-remove');
});

test('removing announces to the screen and never opens the picker', function (): void {
    $html = renderFileField('removable');

    // The zone itself is the pick button, so the remove click must not bubble
    // into it; the decision (confirm, delete) belongs to the screen.
    expect($html)->toContain('field-drop-remove')
        ->toContain('x-on:click.stop="remove()"')
        ->toContain("field: 'logo'")
        ->toContain('x-show="preview"');
});

test('the zone resets when the screen says its file went through', function (): void {
    expect(renderFileField('removable'))
        ->toContain('x-on:file-reset.window')
        ->toContain('reset()');
});
