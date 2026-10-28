<?php

declare(strict_types=1);

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\HasColumns;
use App\Models\AdoptionNudge;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\Currency;
use App\Models\CurrentStatus;
use App\Models\MentionGoal;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\SocialNetwork;
use App\Models\SupportReply;
use App\Models\SupportTicketKind;
use App\Models\TaxCondition;
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
    'currency' => ['currency', Currency::class, ['code', 'name']],
    'business-sector' => ['business-sector', BusinessSector::class, ['code', 'name', 'description']],
    'tax-condition' => ['tax-condition', TaxCondition::class, ['code', 'name', 'country']],
    'social-network' => ['social-network', SocialNetwork::class, ['name', 'abbreviation']],
    'country' => ['country', Country::class, ['code', 'name']],
    'region' => ['region', Region::class, ['name', 'province', 'country']],
    'country-holiday' => ['country-holiday', CountryHoliday::class, ['country', 'name', 'when']],
    'business-activity' => ['business-activity', BusinessActivity::class, ['code', 'name', 'sector']],
    'service-type' => ['service-type', ServiceType::class, ['code', 'name', 'sector', 'modality', 'attributes']],
    'service-attribute' => ['service-attribute', ServiceAttribute::class, ['code', 'name', 'description', 'type']],
    'service-modality' => ['service-modality', ServiceModality::class, ['code', 'name', 'description']],
    'support-reply' => ['support-reply', SupportReply::class, ['name', 'body']],
    'adoption-nudge' => ['adoption-nudge', AdoptionNudge::class, ['stepLabel', 'subject', 'body']],
    'support-ticket-kind' => ['support-ticket-kind', SupportTicketKind::class, ['code', 'name']],
]);

/**
 * The class the web's cell carries for each kind: a kind is a promise the two
 * tables keep, so a monospace cell on the web is a monospace cell on the phone.
 * Plain text, a yes/no and the status tag carry none of these.
 */
const CELL_CLASSES = [
    'name' => 'catalog-cell-name',
    'code' => 'catalog-code',
    'symbol' => 'catalog-cell-sym',
    'number' => 'catalog-cell-num',
    'active' => 'catalog-status',
    'badge' => 'status-tag',
    'list' => 'catalog-chip',
];

/** The editor's `<td>` that paints this row key, as written in its source. */
function cellOf(string $source, string $key): string
{
    preg_match('/<td\b(?:(?!<\/td>).)*?row\.'.preg_quote($key, '/').'\b.*?<\/td>/s', $source, $found);

    return $found[0] ?? '';
}

/** The table header as the editor rendered it: label, whether it takes the free width, and whether it is a number's. */
function renderedHeader(string $component): array
{
    // `<th` followed by a space or `>`, so `<thead>` is not read as a cell.
    preg_match_all('/<th(\s[^>]*)?>(.*?)<\/th>/s', Livewire::test('catalog.'.$component)->html(), $found, PREG_SET_ORDER);

    return collect($found)
        ->map(fn (array $th): array => [
            'label' => trim(strip_tags($th[2])),
            'fill' => str_contains($th[1] ?? '', 'catalog-col-fill'),
            'num' => str_contains($th[1] ?? '', 'is-num'),
        ])
        ->filter(fn (array $th): bool => $th['label'] !== '')
        ->values()
        ->all();
}

it('draws the header from the model\'s columns, in order, with the wide one and a number\'s alignment marked', function (string $component, string $model): void {
    $expected = collect((new $model)->catalogColumns())
        ->map(fn (CatalogColumnDto $column): array => ['label' => $column->label, 'fill' => $column->fill, 'num' => $column->cell === CatalogCell::Number])
        ->all();

    expect(renderedHeader($component))->toBe($expected);
})->with('masters with columns');

it('only names keys the row really carries', function (string $component, string $model): void {
    // A holiday has no factory: it hangs from a country and carries one of its four date shapes.
    $model === CountryHoliday::class
        ? CountryHoliday::create(['country_id' => Country::factory()->create()->id, 'name' => 'Día de prueba', 'month' => 3, 'day' => 9])
        : $model::factory()->create();

    $keys = array_keys((new $model)->catalogRows()->firstOrFail());

    foreach ((new $model)->catalogColumns() as $column) {
        foreach (array_filter([$column->key, $column->sub, $column->tag, $column->icon, ...$column->searchAlso]) as $key) {
            expect($keys)->toContain($key);
        }
    }
})->with('masters with columns');

it('has its cells in the editor for every column, and no list of its own', function (string $component, string $model): void {
    $source = file_get_contents(resource_path('views/components/catalog/⚡'.$component.'.blade.php'));

    expect($source)->toContain(':columns="$this->tableColumns"')
        ->and($source)->toContain(':search="$this->searchKeys"');

    // The cell of each column reads that very key of the row: a renamed key would paint an empty cell.
    foreach ((new $model)->catalogColumns() as $column) {
        expect($source)->toContain('row.'.$column->key);
    }
})->with('masters with columns');

it('paints each cell on the web with the class its kind promises the phone', function (string $component, string $model): void {
    $source = file_get_contents(resource_path('views/components/catalog/⚡'.$component.'.blade.php'));

    foreach ((new $model)->catalogColumns() as $column) {
        $cell = cellOf($source, $column->key);
        // A name with a line under it is the web's two-line `catalog-cell-primary`, and that line reads its key.
        $promised = $column->sub !== null ? 'catalog-cell-primary' : (CELL_CLASSES[$column->cell->value] ?? null);

        foreach (array_filter([$column->sub, $column->tag]) as $carried) {
            expect($cell)->toContain('row.'.$carried);
        }

        // A cell not found would let every check below pass on an empty string.
        expect($cell)->not->toBe('');

        if ($promised !== null) {
            expect($cell)->toContain($promised);
        } elseif ($column->cell === CatalogCell::Text) {
            // Plain text on the phone is plain on the web too: no monospace class hiding in the cell.
            foreach (['catalog-cell-sym', 'catalog-code', 'catalog-cell-num', 'font-mono'] as $mono) {
                expect($cell)->not->toContain($mono);
            }
        }
    }
})->with('masters with columns');

it('names the word and tone of every value of a badge column, and of no other kind', function (string $component, string $model): void {
    foreach ((new $model)->catalogColumns() as $column) {
        if ($column->cell === CatalogCell::Badge) {
            expect($column->badges)->not->toBeEmpty();

            foreach ($column->badges as $badge) {
                expect($badge['label'])->not->toBeEmpty()
                    ->and($badge['tone'])->toBeIn(['neutral', 'info', 'brand', 'success', 'warning', 'danger']);
            }
        } else {
            expect($column->badges)->toBe([]);
        }
    }
})->with('masters with columns');

it('draws a copy button beside a copiable cell, with the words the phone is given', function (string $component, string $model): void {
    $source = file_get_contents(resource_path('views/components/catalog/⚡'.$component.'.blade.php'));

    foreach ((new $model)->catalogColumns() as $column) {
        $cell = cellOf($source, $column->key);

        if ($column->copy !== null) {
            // It copies THIS column's text, and only says so once the clipboard took it.
            expect($cell)->toContain('navigator.clipboard?.writeText(row.'.$column->key.').then(')
                ->and($cell)->toContain('x-on:click.stop')
                ->and($column->copy['label'])->not->toBeEmpty()
                ->and($column->copy['done'])->not->toBeEmpty();
        } else {
            expect($cell)->not->toContain('navigator.clipboard');
        }
    }
})->with('masters with columns');

it('searches the same fields the editors searched before they shared the list', function (string $component, string $model, array $keys): void {
    expect(CatalogColumnDto::searchKeys((new $model)->catalogColumns()))->toBe($keys);
})->with('masters with columns');

it('says on and off in words for an on/off or yes/no column, and in nothing else', function (string $component, string $model): void {
    foreach ((new $model)->catalogColumns() as $column) {
        if (in_array($column->cell, [CatalogCell::Active, CatalogCell::Flag], true)) {
            expect($column->on)->not->toBeEmpty()->and($column->off)->not->toBeEmpty();
        } else {
            expect($column->on)->toBeNull()->and($column->off)->toBeNull();
        }
    }
})->with('masters with columns');
