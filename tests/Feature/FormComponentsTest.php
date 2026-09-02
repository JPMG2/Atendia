<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| <x-ui.input>
|--------------------------------------------------------------------------
*/
test('the input renders a label, control and field, deriving the id from the name', function (): void {
    $html = Blade::render('<x-ui.input label="Email" name="email" />');

    expect($html)
        ->toContain('class="field"')
        ->toContain('field-control')
        ->toContain('class="field-input"')
        ->toContain('id="in-email"')
        ->toContain('name="email"')
        ->toContain('Email');
});

test('the input shows an error and switches the control to the error state', function (): void {
    $html = Blade::render('<x-ui.input name="email" error="Required" />');

    expect($html)->toContain('field-error')->toContain('Required');
});

test('the input wires the Alpine errors bag when given an alpine-error key', function (): void {
    // Same capability as <x-inputsform.input>: the front guard paints the
    // border and the message without a server round-trip.
    $html = Blade::render('<x-ui.input name="email" alpine-error="email" :hint="\'Un hint\'" />');

    expect($html)->toContain("x-bind:class=\"{ 'field-error': !!(errors.email) }\"")
        ->toContain('x-text="errors.email"')
        ->toContain('x-show="!(errors.email)"');
});

test('the input forwards attributes like placeholder and wire:model to the control', function (): void {
    $html = Blade::render('<x-ui.input name="email" placeholder="tu@correo" wire:model="email" />');

    expect($html)->toContain('placeholder="tu@correo"')->toContain('wire:model="email"');
});

test('the input renders a left icon when given one', function (): void {
    expect(Blade::render('<x-ui.input icon="bell" />'))->toContain('<svg');
});

/*
|--------------------------------------------------------------------------
| <x-ui.textarea> & <x-ui.select>
|--------------------------------------------------------------------------
*/
test('the textarea renders its slot as content', function (): void {
    $html = Blade::render('<x-ui.textarea label="Notas" name="notes">hola</x-ui.textarea>');

    expect($html)->toContain('field-textarea')->toContain('hola')->toContain('name="notes"');
});

test('the select builds options from an associative array and a placeholder', function (): void {
    $html = Blade::render('<x-ui.select name="rubro" :options="$opts" placeholder="Elegí" />', [
        'opts' => ['med' => 'Clínica', 'shop' => 'Comercio'],
    ]);

    expect($html)
        ->toContain('field-select')
        ->toContain('<option value="">Elegí</option>')
        ->toContain('<option value="med">Clínica</option>')
        ->toContain('<option value="shop">Comercio</option>');
});

/*
|--------------------------------------------------------------------------
| <x-ui.switch> & <x-ui.checkbox>
|--------------------------------------------------------------------------
*/
test('the switch renders a checkbox with the switch role and reflects checked', function (): void {
    $html = Blade::render('<x-ui.switch name="active" label="Activo" :checked="true" />');

    expect($html)
        ->toContain('class="switch"')
        ->toContain('role="switch"')
        ->toContain('switch-track')
        ->toContain('checked')
        ->toContain('Activo');
});

test('the checkbox renders a title and description and aligns to top with a description', function (): void {
    $html = Blade::render('<x-ui.checkbox name="terms" label="Acepto" description="Términos del servicio" />');

    expect($html)
        ->toContain('checkbox-top')
        ->toContain('checkbox-title')
        ->toContain('Acepto')
        ->toContain('Términos del servicio');
});

/*
|--------------------------------------------------------------------------
| <x-ui.avatar>
|--------------------------------------------------------------------------
*/
test('the avatar falls back to initials and is deterministic per name', function (): void {
    $a = Blade::render('<x-ui.avatar name="Clínica Vida" />');
    $b = Blade::render('<x-ui.avatar name="Clínica Vida" />');

    expect($a)->toContain('avatar-fallback')->toContain('CV')->toBe($b);
});

test('the avatar renders a status dot when given a status', function (): void {
    expect(Blade::render('<x-ui.avatar name="Ana" status="online" />'))
        ->toContain('avatar-status')
        ->toContain('var(--success)');
});

test('the avatar uses a fixed jade tint when given tint=brand, ignoring the name hash', function (): void {
    expect(Blade::render('<x-ui.avatar name="Ana" tint="brand" />'))
        ->toContain('var(--jade-100)')
        ->toContain('var(--jade-700)');
});

/*
|--------------------------------------------------------------------------
| <x-ui.alert>
|--------------------------------------------------------------------------
*/
test('the alert applies its variant class and can be dismissible via Alpine', function (): void {
    $html = Blade::render('<x-ui.alert variant="danger" title="Ups" dismissible>Algo falló</x-ui.alert>');

    expect($html)
        ->toContain('alert alert-danger')
        ->toContain('Ups')
        ->toContain('Algo falló')
        ->toContain('x-data="{ show: true }"');
});

test('an invalid alert variant falls back to info', function (): void {
    expect(Blade::render('<x-ui.alert variant="nope">hi</x-ui.alert>'))->toContain('alert-info');
});

/*
|--------------------------------------------------------------------------
| <x-ui.stat-card>
|--------------------------------------------------------------------------
*/
test('the stat card renders label, value and a trend arrow with its delta', function (): void {
    $html = Blade::render('<x-ui.stat-card label="Turnos hoy" value="24" delta="+12%" trend="up" icon="calendar-check" />');

    expect($html)
        ->toContain('stat-card')
        ->toContain('Turnos hoy')
        ->toContain('24')
        ->toContain('↑ +12%')
        ->toContain('<svg');
});

/*
|--------------------------------------------------------------------------
| <x-ui.tabs>
|--------------------------------------------------------------------------
*/
test('the tabs render a button per tab and start on the default', function (): void {
    $html = Blade::render('<x-ui.tabs :tabs="$tabs" default="chats" />', [
        'tabs' => [['value' => 'turnos', 'label' => 'Turnos'], ['value' => 'chats', 'label' => 'Conversaciones']],
    ]);

    expect($html)
        ->toContain('role="tablist"')
        ->toContain('Turnos')
        ->toContain('Conversaciones')
        ->toContain("tab: 'chats'");
});

/*
|--------------------------------------------------------------------------
| <x-ui.stepper>
|--------------------------------------------------------------------------
*/
test('the stepper renders a step per entry and starts on the default', function (): void {
    $html = Blade::render('<x-ui.stepper :steps="$steps" default="main" />', [
        'steps' => [
            ['value' => 'main', 'label' => 'Configuración principal', 'desc' => 'Identidad y domicilio'],
            ['value' => 'commercial', 'label' => 'Datos comerciales', 'desc' => 'Contacto y redes'],
        ],
    ]);

    expect($html)
        ->toContain('class="stepper"')
        ->toContain('Configuración principal')
        ->toContain('Datos comerciales')
        ->toContain('Identidad y domicilio')
        ->toContain("step: 'main'")
        ->toContain('stepper-link');   // the thread that says one step leads to the other
});

test('a locked stepper keeps every step but the first shut, and says what opens it', function (): void {
    $html = Blade::render('<x-ui.stepper :steps="$steps" lockedHint="Guarda primero" />', [
        'steps' => [['value' => 'main', 'label' => 'Uno'], ['value' => 'commercial', 'label' => 'Dos']],
    ]);

    // Locking is the default: a step is only opened once the record exists.
    expect($html)
        ->toContain('unlocked: false')
        ->toContain('isLocked(1)')
        ->toContain('Guarda primero');
});

test('an unlocked stepper opens every step and marks the first as done', function (): void {
    $html = Blade::render('<x-ui.stepper :steps="$steps" :unlocked="true" />', [
        'steps' => [['value' => 'main', 'label' => 'Uno'], ['value' => 'commercial', 'label' => 'Dos']],
    ]);

    expect($html)
        ->toContain('unlocked: true')
        ->toContain('isDone(0)');
});

test('the stepper themes itself through tokens, with no hardcoded hex', function (): void {
    $html = Blade::render('<x-ui.stepper :steps="$steps" />', [
        'steps' => [['value' => 'main', 'label' => 'Uno']],
    ]);

    expect($html)->not->toContain('#');
});

/*
|--------------------------------------------------------------------------
| Golden rule: theme-aware, no hardcoded hex (checkbox's white tick aside)
|--------------------------------------------------------------------------
*/
test('form and data components theme themselves through tokens, no hardcoded hex', function (): void {
    foreach ([
        '<x-ui.input label="A" name="a" />',
        '<x-ui.select name="a" :options="$o" />',
        '<x-ui.switch name="a" label="A" />',
        '<x-ui.avatar name="Ana Paz" status="online" />',
        '<x-ui.alert variant="success">ok</x-ui.alert>',
        '<x-ui.stat-card label="A" value="1" delta="+1%" icon="bell" />',
    ] as $template) {
        expect(Blade::render($template, ['o' => ['x' => 'X']]))->not->toContain('#');
    }
});
