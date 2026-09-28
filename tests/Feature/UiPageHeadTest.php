<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('the page head renders title, subtitle and the far-side slot', function (): void {
    $html = Blade::render(
        '<x-ui.page-head :title="$title" :sub="$sub"><span class="aside">3 hoy</span></x-ui.page-head>',
        ['title' => 'Conversaciones', 'sub' => 'Lo que respondió tu asistente.'],
    );

    expect($html)
        ->toContain('class="page-head"')
        ->toContain('<h1 class="page-head-title">Conversaciones</h1>')
        ->toContain('<p class="page-head-sub">Lo que respondió tu asistente.</p>')
        ->toContain('<span class="aside">3 hoy</span>')
        ->not->toContain('bp-back');
});

test('without a subtitle no empty paragraph is printed', function (): void {
    expect(Blade::render('<x-ui.page-head title="Equipo" />'))->not->toContain('page-head-sub');
});

test('a back link and a lead slot sit before the title', function (): void {
    $html = Blade::render(
        '<x-ui.page-head title="Ajustes" back="/dashboard" back-label="Volver"><x-slot:lead><i class="avatar"></i></x-slot:lead></x-ui.page-head>',
    );

    expect($html)
        ->toContain('bp-head')
        ->toContain('href="/dashboard"')
        ->toContain('aria-label="Volver"')
        ->and(strpos($html, 'bp-back'))->toBeLessThan(strpos($html, 'class="avatar"'))
        ->and(strpos($html, 'class="avatar"'))->toBeLessThan(strpos($html, 'page-head-title'));
});

test('the inline slot rides on the same line as the title', function (): void {
    $html = Blade::render(
        '<x-ui.page-head title="Hola"><x-slot:inline><a class="status-tag">Sin conectar</a></x-slot:inline></x-ui.page-head>',
    );

    expect($html)->toMatch('/flex-wrap items-center gap-3">\s*<h1 class="page-head-title">Hola<\/h1>\s*<a class="status-tag">/');
});
