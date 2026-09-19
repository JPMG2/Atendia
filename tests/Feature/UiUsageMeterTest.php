<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('the usage meter renders label, figures and its color state', function (): void {
    $html = Blade::render(
        '<x-ui.usage-meter :label="$label" :text="$text" :percent="80" state="warn" />',
        ['label' => 'Conversaciones', 'text' => '240 de 300'],
    );

    expect($html)
        ->toContain('Conversaciones')
        ->toContain('240 de 300')
        ->toContain('data-state="warn"')
        ->toContain('width: 80%');
});

test('the fill never spills outside the track, whatever the numbers say', function (): void {
    $over = Blade::render('<x-ui.usage-meter label="x" text="x" :percent="140" state="over" />');
    $negative = Blade::render('<x-ui.usage-meter label="x" text="x" :percent="-5" />');

    expect($over)->toContain('width: 100%')
        ->and($negative)->toContain('width: 0%');
});
