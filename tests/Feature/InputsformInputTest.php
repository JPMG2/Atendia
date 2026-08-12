<?php

declare(strict_types=1);

/*
 * Golden-rule behaviours of the <x-inputsform.input> primitive: sizes (s/m/l),
 * readonly/disabled states, in-field icon, Laravel + Alpine errors (red border
 * via aria-invalid), and the description/error coexisting without overlapping.
 */

it('renders a labelled input with its name and derived id', function () {
    $this->blade('<x-inputsform.input label="Código ISO" name="code" />')
        ->assertSee('field-control', false)
        ->assertSee('field-input', false)
        ->assertSee('name="code"', false)
        ->assertSee('id="if-code"', false)
        ->assertSee('Código ISO');
});

it('applies the s / m / l sizes', function () {
    $this->blade('<x-inputsform.input name="a" size="s" />')->assertSee('field-sm', false);
    $this->blade('<x-inputsform.input name="b" size="l" />')->assertSee('field-lg', false);
    $this->blade('<x-inputsform.input name="c" size="m" />')
        ->assertDontSee('field-sm', false)
        ->assertDontSee('field-lg', false);
});

it('marks the readonly and disabled states', function () {
    $this->blade('<x-inputsform.input name="a" readonly />')
        ->assertSee('is-readonly', false)
        ->assertSee('readonly', false);

    $this->blade('<x-inputsform.input name="b" disabled />')
        ->assertSee('is-disabled', false)
        ->assertSee('disabled', false);
});

it('adds a required asterisk (and aria-required) when required, without the native attribute', function () {
    $this->blade('<x-inputsform.input label="Código ISO" name="code" required />')
        ->assertSee('field-required', false)
        ->assertSeeInOrder(['Código ISO', '*'], false)
        ->assertSee('aria-required="true"', false); // a11y kept; native `required` is stripped

    $this->blade('<x-inputsform.input label="Código ISO" name="code" />')
        ->assertDontSee('field-required', false);
});

it('renders a leading icon inside the control', function () {
    $this->blade('<x-inputsform.input name="a" icon="globe" />')
        ->assertSee('field-icon', false)
        ->assertSee('<svg', false);
});

it('shows a Laravel error with the red-border hook and the message', function () {
    $this->blade('<x-inputsform.input name="code" error="Requerido" />')
        ->assertSee('field-error', false)         // red border class
        ->assertSee('aria-invalid="true"', false) // red border also via :has() hook
        ->assertSee('field-error-text', false)
        ->assertSee('Requerido');
});

it('keeps the description and the error both visible without overlapping', function () {
    // Both render, stacked inside .field-meta (never one on top of the other).
    $this->blade('<x-inputsform.input name="code" hint="3 letras (ARS, USD)" error="Requerido" />')
        ->assertSee('field-meta', false)
        ->assertSeeInOrder(['field-hint', '3 letras (ARS, USD)', 'field-error-text', 'Requerido'], false);
});

it('supports an Alpine-driven error via aria-invalid binding and x-show/x-text', function () {
    // The component takes only the field key and builds the `errors.<key>` expression itself.
    $this->blade('<x-inputsform.input name="code" alpine-error="code" />')
        ->assertSee('x-bind:aria-invalid', false)
        ->assertSee('errors.code', false)
        ->assertSee('x-show', false)
        ->assertSee('x-text', false);
});
