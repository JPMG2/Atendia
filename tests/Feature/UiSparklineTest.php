<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| <x-ui.sparkline>
|--------------------------------------------------------------------------
| The shape of a run of months inside a table row. It draws nothing rather
| than draw a line that is not one, and it never invents a slope: a flat run
| stays flat instead of swinging on rounding noise.
*/

function sparkline(array $values, string $label = 'Tendencia'): string
{
    return Blade::render(
        '<x-ui.sparkline :values="$values" :label="$label" />',
        ['values' => $values, 'label' => $label],
    );
}

test('it draws one point per period, oldest on the left', function (): void {
    $svg = sparkline([0, 5, 10]);

    expect($svg)->toContain('<polyline')
        ->and($svg)->toContain('points="0,19 52,10 104,1"');
});

test('it draws nothing at all with fewer than two periods', function (): void {
    expect(trim(sparkline([])))->toBe('')
        ->and(trim(sparkline([7])))->toBe('');
});

test('a run that never moves stays a flat line instead of swinging', function (): void {
    $svg = sparkline([4, 4, 4]);

    // Every point on the same y: scaled against its own maximum a flat run
    // would otherwise be drawn as a climb from the floor to the ceiling.
    expect($svg)->toContain('points="0,1 52,1 104,1"');
});

test('a run of nothing sits on the middle line, not on the floor', function (): void {
    expect(sparkline([0, 0, 0]))->toContain('points="0,10 52,10 104,10"');
});

test('it marks the last period so the month being read stands out', function (): void {
    expect(sparkline([1, 2]))->toContain('<circle cx="104"');
});

test('it carries its own label for whoever cannot see the shape', function (): void {
    expect(sparkline([1, 2], 'Consumo de 12 meses'))
        ->toContain('role="img"')
        ->toContain('aria-label="Consumo de 12 meses"');
});

test('it never prints a colour of its own', function (): void {
    expect(sparkline([1, 2, 3]))
        ->toContain('currentColor')
        ->not->toMatch('/#[0-9a-fA-F]{3,6}/');
});
