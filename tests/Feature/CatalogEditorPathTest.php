<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The nine editors no longer declare `path`: since every form keeps its DTO in
// BaseCatalogForm::$data, the value is the same for all of them and lives once as
// the default of <x-catalog.master>. If that default ever breaks, Alpine's submit()
// reads `$wire.get('.code')` instead of `$wire.get('form.data.code')` and front-end
// validation silently passes everything to the server — no error, just a lost guard.
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
