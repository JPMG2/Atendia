<?php

declare(strict_types=1);

/*
 * Golden-rule behaviours of the <x-inputsform.textarea> primitive: the
 * multiline sibling of the input, sharing the same wrapper anatomy (single
 * focus ring, error by name, hint/error stacked without overlapping).
 */

it('renders a labelled textarea with its name, rows and derived id', function () {
    $this->blade('<x-inputsform.textarea label="Descripción" name="description" :rows="5">Hola</x-inputsform.textarea>')
        ->assertSee('field-control-multiline', false)
        ->assertSee('field-input', false)
        ->assertSee('name="description"', false)
        ->assertSee('id="if-description"', false)
        ->assertSee('rows="5"', false)
        ->assertSee('Descripción')
        ->assertSee('Hola');
});

it('marks the required state without the native attribute', function () {
    $this->blade('<x-inputsform.textarea label="Descripción" name="d" required />')
        ->assertSee('field-required', false)
        ->assertSee('aria-required="true"', false)
        ->assertDontSee('required>', false);
});

it('shows an opt-in character counter wired to its maxlength', function () {
    $this->blade('<x-inputsform.textarea name="d" maxlength="500" counter />')
        ->assertSee('field-counter', false)
        ->assertSee('/500', false)
        ->assertSee('count >= 450', false)
        ->assertSee('maxlength="500"', false);
});

it('keeps the counter out without the opt-in or without a maxlength', function () {
    $this->blade('<x-inputsform.textarea name="d" maxlength="500" />')
        ->assertDontSee('field-counter', false);

    $this->blade('<x-inputsform.textarea name="d" counter />')
        ->assertDontSee('field-counter', false);
});

it('shows a Laravel error with the red-border hook and the message', function () {
    $this->blade('<x-inputsform.textarea name="d" error="Requerido" />')
        ->assertSee('field-error', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('field-error-text', false)
        ->assertSee('Requerido');
});

it('wires the Alpine error bag by key', function () {
    $this->blade('<x-inputsform.textarea name="d" alpine-error="description" />')
        ->assertSee('errors.description', false)
        ->assertSee('x-cloak', false);
});

it('keeps the hint and the error stacked in the meta block', function () {
    $this->blade('<x-inputsform.textarea name="d" hint="Una guía" error="Requerido" />')
        ->assertSee('field-meta', false)
        ->assertSeeInOrder(['Una guía', 'Requerido']);
});
