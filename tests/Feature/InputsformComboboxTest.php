<?php

declare(strict_types=1);

/**
 * The currency options every case renders with, in the shape the country editor
 * hands over.
 *
 * @return array<int, array{value: int, label: string}>
 */
function comboboxOptions(): array
{
    return [
        ['value' => 1, 'label' => 'ARS — Peso Argentino'],
        ['value' => 2, 'label' => 'USD — Dólar Estadounidense'],
    ];
}

test('it renders the label, the required mark and the search input', function (): void {
    $this->blade(
        '<x-inputsform.combobox label="Moneda" required name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )
        ->assertSee('Moneda')
        ->assertSee('field-required', false)
        ->assertSee('aria-required="true"', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('id="if-currency_id"', false);
});

test('the real value travels in a hidden input, so wire:model binds to the id and not to the typed text', function (): void {
    // The visible field is a search box: if it carried the name, a submit would
    // send "ARS — Peso Argentino" instead of the id.
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" wire:model="form.data.currency_id" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('type="hidden"')
        ->toContain('name="currency_id"')
        ->toContain('wire:model="form.data.currency_id"');

    // The wire:model belongs to the hidden field, never to the search box.
    preg_match('/<input\s+type="text"(.*?)\/>/s', $html, $search);

    expect($search[1] ?? '')->not->toContain('wire:model')
        ->and($search[1] ?? '')->not->toContain('name=');
});

test('it normalizes the three accepted option shapes into value/label pairs', function (): void {
    $assoc = $this->blade('<x-inputsform.combobox name="a" :options="[\'ars\' => \'Peso Argentino\']" />')->__toString();
    $list = $this->blade('<x-inputsform.combobox name="b" :options="[\'ARS\', \'USD\']" />')->__toString();
    $pairs = $this->blade(
        '<x-inputsform.combobox name="c" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($assoc)->toContain('Peso Argentino')
        ->toContain('ars')
        ->and($list)->toContain('USD')
        ->and($pairs)->toContain('Dólar Estadounidense');
});

test('a backend error paints the field and is shown, exactly like the input does', function (): void {
    $this->blade(
        '<x-inputsform.combobox name="currency_id" error="La moneda es obligatoria." :options="$options" />',
        ['options' => comboboxOptions()],
    )
        ->assertSee('field-error', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('id="if-currency_id-err"', false)
        ->assertSee('La moneda es obligatoria.');
});

test('an alpine error key wires the same red border and message against the errors bag', function (): void {
    // Same contract as <x-inputsform.input>: the Blade never writes the expression,
    // it only receives the key.
    $this->blade(
        '<x-inputsform.combobox name="currency_id" alpine-error="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )
        ->assertSee('x-bind:aria-invalid="!!(errors.currency_id) || null"', false)
        ->assertSee('x-text="errors.currency_id"', false);
});

test('the hint and the error live stacked in field-meta so they never overlap', function (): void {
    $this->blade(
        '<x-inputsform.combobox name="currency_id" hint="Moneda de curso legal" error="Requerido" :options="$options" />',
        ['options' => comboboxOptions()],
    )
        ->assertSee('field-meta', false)
        ->assertSee('field-hint', false)
        ->assertSee('field-error-text', false);
});

test('the empty-results copy comes from a lang file', function (): void {
    $html = $this->blade('<x-inputsform.combobox name="currency_id" :options="[]" />')->__toString();

    expect($html)->toContain(__('forms.combobox.empty'))
        ->not->toContain('forms.combobox.');
});

test('it preselects the option matching the given value', function (): void {
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" :value="2" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('initial: 2');
});

test('with no value it starts empty instead of silently picking the first option', function (): void {
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('initial: null');
});

test('the search input is not a native select, which is the whole point of the component', function (): void {
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->not->toContain('<select')
        ->toContain('role="listbox"')
        ->toContain('role="option"');
});

test('it never hardcodes a colour and uses only x-icon for its glyphs', function (): void {
    // Golden rules, pinned on the source: the guardian test covers every view, this
    // pins the two rules this component could plausibly break.
    $source = file_get_contents(resource_path('views/components/inputsform/combobox.blade.php'));

    expect($source)->not->toMatch('/#[0-9a-fA-F]{3}\b/')
        ->not->toContain('data-lucide')
        ->toContain('<x-icon name="chevron-down"')
        ->toContain('<x-icon name="check"');
});

test('a combobox with no loading target renders no wire:loading at all', function (): void {
    // The blocking is opt-in: a standalone combobox must not gain a spinner or a
    // wire:target that would freeze it on any request of the screen.
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->not->toContain('wire:loading')
        ->not->toContain('wire:target')
        ->not->toContain('combo-spinner')
        // The chevron is still there, just without the loading pair around it.
        ->toContain('combo-toggle');
});

test('a combobox that depends on another field blocks itself while that field is in flight', function (): void {
    // Picking from the stale list saves a value the server already dropped, so
    // the field has to be unusable until its own list has landed.
    $html = $this->blade(
        '<x-inputsform.combobox name="province_id" loading="form.data.country_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('wire:loading.attr="disabled"')
        ->toContain('wire:loading.class="is-loading"')
        // Scoped to that one property: any other request on the screen must not
        // freeze this combobox by accident.
        ->toContain('wire:target="form.data.country_id"')
        ->not->toContain('wire:target="form.data.province_id"');
});

test('the loading combobox swaps its chevron for a labelled spinner', function (): void {
    $html = $this->blade(
        '<x-inputsform.combobox name="province_id" loading="form.data.country_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('combo-spinner')
        ->toContain('wire:loading.remove')
        ->toContain('role="status"')
        // Copy comes from the translation files, never hardcoded in the markup.
        ->toContain(__('forms.combobox.loading'));
});

test('the spinner glyph is registered, or the icon draws nothing', function (): void {
    expect(config('icons.loader-circle'))->not->toBeNull();
});

test('the combobox carries a clear button, so wiping a picked option is one click', function (): void {
    // Without it, changing your mind means selecting the whole label and deleting
    // it by hand before you can search again.
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('combo-clear')
        ->toContain('clear()')
        ->toContain(__('forms.combobox.clear'))
        // Only when there is something to clear: a fixed 'x' over an empty field
        // is noise, and invites a click that does nothing.
        ->toContain('x-show="selected || query"');
});

test('the clear button is out of the tab order and never submits', function (): void {
    // It is a shortcut for the mouse; the keyboard already clears by emptying the
    // field. And an unnamed <button> inside a form defaults to type=submit.
    $html = $this->blade(
        '<x-inputsform.combobox name="currency_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    expect($html)->toContain('<button type="button" class="combo-clear" tabindex="-1"');
});

test('a loading combobox cannot be cleared either', function (): void {
    // Clearing mid-flight would push a null the incoming response is about to
    // overwrite, leaving the field and the server disagreeing.
    $html = $this->blade(
        '<x-inputsform.combobox name="province_id" loading="form.data.country_id" :options="$options" />',
        ['options' => comboboxOptions()],
    )->__toString();

    $clearButton = substr($html, strpos($html, 'combo-clear'), 400);

    expect($clearButton)->toContain('wire:loading.attr="disabled"');
});
