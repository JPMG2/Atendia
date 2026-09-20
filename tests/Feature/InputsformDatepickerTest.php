<?php

declare(strict_types=1);

test('it renders the label, the calendar icon and the display input', function (): void {
    $this->blade('<x-inputsform.datepicker label="Fechas" name="dates" />')
        ->assertSee('Fechas')
        ->assertSee('field-icon', false)
        ->assertSee('inputsformDatepicker', false)
        ->assertSee('id="if-dates"', false);
});

test('the real value travels in a hidden input, so wire:model binds ISO and not the shown date', function (): void {
    $html = $this->blade('<x-inputsform.datepicker name="dates" wire:model.live="dates" />')->__toString();

    expect($html)->toContain('type="hidden"')
        ->toContain('name="dates"')
        ->toContain('wire:model.live="dates"');
});

test('range mode reaches the alpine component and an invalid mode falls back to single', function (): void {
    expect($this->blade('<x-inputsform.datepicker name="dates" mode="range" />')->__toString())
        ->toContain("mode: 'range'")
        ->and($this->blade('<x-inputsform.datepicker name="dates" mode="banana" />')->__toString())
        ->toContain("mode: 'single'");
});

test('a preselected value seeds both the alpine state and the hidden input', function (): void {
    $html = $this->blade('<x-inputsform.datepicker name="dates" value="2026-09-01..2026-09-10" />')->__toString();

    expect($html)->toContain("initial: '2026-09-01..2026-09-10'")
        ->toContain('value="2026-09-01..2026-09-10"');
});

test('the clear button names its action and only shows with a value', function (): void {
    $this->blade('<x-inputsform.datepicker name="dates" />')
        ->assertSee(__('forms.datepicker.clear'))
        ->assertSee('x-show="hasValue"', false);
});
