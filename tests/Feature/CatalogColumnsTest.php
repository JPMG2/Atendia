<?php

declare(strict_types=1);

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\HasColumns;
use App\Models\CurrentStatus;
use App\Models\MentionGoal;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The masters that say their own columns: the web table and the mobile API read
 * the same list, so these tie each editor to its model.
 *
 * @return array<string, array{0: string, 1: class-string<HasColumns>, 2: list<string>}>
 */
dataset('masters with columns', [
    'status' => ['status', CurrentStatus::class, ['name']],
    'mention-goal' => ['mention-goal', MentionGoal::class, ['achievement', 'caption']],
    'province' => ['province', Province::class, ['name', 'country']],
]);

/** The table header as the editor rendered it: label, and whether it takes the free width. */
function renderedHeader(string $component): array
{
    // `<th` followed by a space or `>`, so `<thead>` is not read as a cell.
    preg_match_all('/<th(\s[^>]*)?>(.*?)<\/th>/s', Livewire::test('catalog.'.$component)->html(), $found, PREG_SET_ORDER);

    return collect($found)
        ->map(fn (array $th): array => ['label' => trim(strip_tags($th[2])), 'fill' => str_contains($th[1] ?? '', 'catalog-col-fill')])
        ->filter(fn (array $th): bool => $th['label'] !== '')
        ->values()
        ->all();
}

it('draws the header from the model\'s columns, in order and with the wide one marked', function (string $component, string $model): void {
    $expected = collect((new $model)->catalogColumns())
        ->map(fn (CatalogColumnDto $column): array => ['label' => $column->label, 'fill' => $column->fill])
        ->all();

    expect(renderedHeader($component))->toBe($expected);
})->with('masters with columns');

it('only names keys the row really carries', function (string $component, string $model): void {
    $model::factory()->create();

    $keys = array_keys((new $model)->catalogRows()->firstOrFail());

    foreach ((new $model)->catalogColumns() as $column) {
        expect($keys)->toContain($column->key);
    }
})->with('masters with columns');

it('has its cells in the editor for every column, and no list of its own', function (string $component): void {
    $source = file_get_contents(resource_path('views/components/catalog/⚡'.$component.'.blade.php'));

    expect($source)->toContain(':columns="$this->tableColumns"')
        ->and($source)->toContain(':search="$this->searchKeys"');
})->with('masters with columns');

it('searches the same fields the editors searched before they shared the list', function (string $component, string $model, array $keys): void {
    expect(CatalogColumnDto::searchKeys((new $model)->catalogColumns()))->toBe($keys);
})->with('masters with columns');

it('says on and off in words for an on/off column, and in nothing else', function (string $component, string $model): void {
    foreach ((new $model)->catalogColumns() as $column) {
        if ($column->cell === CatalogCell::Active) {
            expect($column->on)->not->toBeEmpty()->and($column->off)->not->toBeEmpty();
        } else {
            expect($column->on)->toBeNull()->and($column->off)->toBeNull();
        }
    }
})->with('masters with columns');
