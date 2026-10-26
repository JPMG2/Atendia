<?php

declare(strict_types=1);

use App\Classes\Main\CompanyProfile;
use App\Dto\CompanyDto;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The keys the sheet prints, flat. */
function sheetKeys(): array
{
    return collect((new CompanyProfile)->sections)->flatMap(fn (array $section): array => array_column($section['fields'], 'key'))->all();
}

test('every field the web form edits is on the sheet the app reads, under the label the form gives it', function (): void {
    $form = file_get_contents(resource_path('views/components/admin/company/⚡index.blade.php'));

    // The labels of the form's own inputs: `:label="__('company.fields.x')"`.
    preg_match_all('/:label="__\(\'company\.fields\.([a-z_]+)\'\)"/', $form, $matches);
    $labelled = array_unique($matches[1]);

    expect($labelled)->not->toBeEmpty();

    foreach ($labelled as $key) {
        expect(sheetKeys())->toContain($key);
    }

    // And the other way: the sheet holds nothing the form does not edit.
    expect(array_diff(sheetKeys(), $labelled))->toBe([]);
});

test('the sheet covers every column of the company the form saves, apart from the logos and the ids it names', function (): void {
    $columns = array_keys((new CompanyDto)->toArray());
    $named = ['country_id' => 'country', 'province_id' => 'province', 'region_id' => 'region', 'tax_condition_id' => 'tax_condition', 'text_copyright' => 'copyright'];
    $skipped = ['logo_path_light', 'logo_path_dark'];

    $expected = collect($columns)
        ->reject(fn (string $column): bool => in_array($column, $skipped, true))
        ->map(fn (string $column): string => $named[$column] ?? $column)
        ->values()
        ->all();

    expect(array_diff($expected, sheetKeys()))->toBe([])
        ->and(array_diff(sheetKeys(), $expected))->toBe([]);
});
