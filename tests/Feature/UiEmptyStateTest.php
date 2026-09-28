<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('the empty state renders icon tile, h2 title and body', function (): void {
    $html = Blade::render('<x-ui.empty-state icon="users" title="Sin clientes" body="Llegan solos." />');

    expect($html)
        ->toContain('bg-brand-soft')
        ->toContain('<svg')
        ->toMatch('/<h2 class="[^"]*font-display[^"]*">Sin clientes<\/h2>/')
        ->toContain('<p class="text-body mt-1 text-sm">Llegan solos.</p>');
});

test('the slot renders the action under the body, and without one there is no action row', function (): void {
    expect(Blade::render('<x-ui.empty-state icon="users" title="T" body="B"><a href="/x">Go</a></x-ui.empty-state>'))
        ->toContain('<div class="mt-4 flex flex-wrap gap-2"><a href="/x">Go</a></div>')
        ->and(Blade::render('<x-ui.empty-state icon="users" title="T" body="B" />'))
        ->not->toContain('mt-4 flex flex-wrap');
});

test('compact mode drops to a small h3 for blocks nested in a card', function (): void {
    $html = Blade::render('<x-ui.empty-state compact class="mt-4" icon="sparkles" title="Nada" body="Aún." />');

    expect($html)
        ->toContain('<h3 class="text-strong text-sm font-semibold">Nada</h3>')
        ->toContain('flex items-start gap-4 mt-4')
        ->not->toContain('<h2');
});
