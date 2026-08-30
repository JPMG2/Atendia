<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The nine editors no longer declare `path`: it lives once as the component's
// default. Break it and Alpine reads the wrong path, front-end validation
// passes everything through, and the guard is lost with no error to show.
test('every catalog editor seeds Alpine with the shared DTO path', function (string $component): void {
    $html = Livewire::test($component)->html();

    // Js::from renders a plain string as a JS literal (no JSON.parse wrapper), and
    // Blade leaves it alone because it is Htmlable — so it lands verbatim in x-data.
    expect($html)->toContain("path: 'form.data'");
})->with([
    'catalog.business-activity',
    'catalog.business-sector',
    'catalog.country',
    'catalog.currency',
    'catalog.province',
    'catalog.region',
    'catalog.social-network',
    'catalog.status',
    'catalog.tax-condition',
]);
