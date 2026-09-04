<?php

declare(strict_types=1);

use App\Ai\Agents\ProductColumnMapper;
use App\Jobs\ProcessProductImport;
use App\Models\Business;
use App\Models\ProductImport;
use App\Models\User;
use App\Services\ProductImport\ColumnMapper;
use App\Services\ProductImport\ImportFileReader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Wizard step 4 — the spreadsheet import
|--------------------------------------------------------------------------
| Upload → the reader tastes the file, the mapper proposes where each
| column lands (synonyms first, the AI agent only for the leftovers, extra
| as the floor) and ONE confirmation stores the file plus the mapping for
| the queued job. No column is ever rejected: unknown data is knowledge.
*/

function actingAsImporter(): Business
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create()->refresh();
    $user->assignRole('client');

    $business = Business::factory()->create();
    $user->business()->associate($business)->save();

    test()->actingAs($user);

    return $business;
}

/**
 * Livewire's test harness only takes Testing\File instances, so the real
 * workbook bytes are wrapped through createWithContent.
 *
 * @param  list<list<string>>  $rows
 */
function spreadsheetFile(array $rows, string $name = 'inventario.xlsx'): File
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);

    $path = tempnam(sys_get_temp_dir(), 'wizard-import').'.xlsx';
    new Xlsx($spreadsheet)->save($path);

    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
}

test('the reader tastes headers, samples and the row count', function (): void {
    $file = spreadsheetFile([
        ['Producto', 'Cantidad', 'Precio', 'Preparación'],
        ['Ecodoppler', '1', '15000', 'Venir en ayunas'],
        ['Radiografía', '2', '8000', ''],
    ]);

    $summary = new ImportFileReader()->read($file->getRealPath());

    expect($summary['headers'])->toBe(['Producto', 'Cantidad', 'Precio', 'Preparación'])
        ->and($summary['total_rows'])->toBe(2)
        ->and($summary['samples'][0][0])->toBe('Ecodoppler');
});

test('the bakery spreadsheet is mapped by synonyms alone, no AI needed', function (): void {
    ProductColumnMapper::fake();

    $proposal = new ColumnMapper()->map(['Producto', 'Cantidad', 'Precio', 'Descripción'], []);

    expect($proposal['targets'])->toBe(['name', 'stock', 'price', 'description'])
        ->and($proposal['labels'])->toBe(['Producto', 'Cantidad', 'Precio', 'Descripción']);

    ProductColumnMapper::assertNeverPrompted();
});

test('a column the synonyms do not know is asked to the agent', function (): void {
    ProductColumnMapper::fake([
        ['mappings' => [['column' => 'Preparación', 'target' => 'extra', 'label' => 'Preparación']]],
    ]);

    $proposal = new ColumnMapper()->map(['Producto', 'Preparación'], [['Ecodoppler', 'Venir en ayunas']]);

    expect($proposal['targets'])->toBe(['name', 'extra']);

    ProductColumnMapper::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Preparación'));
});

test('the AI being down degrades to extra instead of breaking the upload', function (): void {
    // No fake response defined: the stray-prompt exception plays the outage.
    ProductColumnMapper::fake()->preventStrayPrompts();

    $proposal = new ColumnMapper()->map(['Producto', 'Columna misteriosa'], []);

    expect($proposal['targets'])->toBe(['name', 'extra']);
});

test('a typoed header comes back fixed as an editable label suggestion', function (): void {
    ProductColumnMapper::fake([
        ['mappings' => [['column' => 'Prescio', 'target' => 'price', 'label' => 'Precio']]],
    ]);

    $proposal = new ColumnMapper()->map(['Producto', 'Prescio'], [['Coca 1.5L', '2500']]);

    expect($proposal['targets'])->toBe(['name', 'price'])
        ->and($proposal['labels'])->toBe(['Producto', 'Precio']);
});

test('with no name column the first one takes the role: a list always has names', function (): void {
    ProductColumnMapper::fake()->preventStrayPrompts();

    expect(new ColumnMapper()->map(['Cosa rara', 'Precio'], [])['targets'])->toBe(['name', 'price']);
});

test('uploading opens the review with the proposed mapping', function (): void {
    actingAsImporter();
    ProductColumnMapper::fake();

    Livewire::test('business.step-products')
        ->set('upload', spreadsheetFile([
            ['Producto', 'Precio'],
            ['Pan de campo', '3500'],
        ]))
        ->assertSet('headers', ['Producto', 'Precio'])
        ->assertSet('mapping', ['name', 'price'])
        ->assertSet('labels', ['Producto', 'Precio'])
        ->assertSet('totalRows', 1)
        ->assertSee(__('wizard.products.review_title'));
});

test('confirming stores the file and queues the import for the tenant', function (): void {
    Queue::fake();
    Storage::fake('local');
    $business = actingAsImporter();
    ProductColumnMapper::fake();

    Livewire::test('business.step-products')
        ->set('upload', spreadsheetFile([
            ['Producto', 'Precio', 'Preparación'],
            ['Ecodoppler', '15000', 'Venir en ayunas'],
        ], 'estudios.xlsx'))
        ->call('confirmImport')
        ->assertSet('headers', [])
        ->assertDispatched('wizard:products-imported')
        // The preview asks for the sheet's own first row, never a canned
        // demo product — a doctor must not end up selling car parts.
        ->assertDispatched('wizard:products-updated', products: ['Ecodoppler']);

    $import = ProductImport::query()->sole();

    expect($import->business_id)->toBe($business->id)
        ->and($import->original_name)->toBe('estudios.xlsx')
        ->and($import->status)->toBe('pending')
        ->and($import->total_rows)->toBe(1)
        ->and(collect($import->mapping)->firstWhere('column', 'Preparación'))
        ->toMatchArray(['target' => 'extra', 'label' => 'Preparación']);

    Storage::disk('local')->assertExists($import->path);

    Queue::assertPushed(ProcessProductImport::class, fn (ProcessProductImport $job): bool => $job->importId === $import->id);
});

test('an unreadable file warns and never opens the review', function (): void {
    actingAsImporter();

    Livewire::test('business.step-products')
        ->set('upload', UploadedFile::fake()->createWithContent('vacio.csv', "\n"))
        ->assertSet('headers', [])
        ->assertSet('upload', null);

    expect(ProductImport::query()->count())->toBe(0);
});
