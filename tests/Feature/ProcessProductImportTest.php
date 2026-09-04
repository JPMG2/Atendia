<?php

declare(strict_types=1);

use App\Jobs\ProcessProductImport;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\ProductImport;
use App\Services\ProductImport\ImportFileReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The queued spreadsheet import
|--------------------------------------------------------------------------
| The job turns a confirmed import into products (upsert by name, never a
| delete) and feeds EVERY column verbatim into one knowledge document per
| file, so the assistant answers what no schema anticipated — the lab's
| "Preparación" included.
*/

/**
 * Writes a real workbook into the fake local disk and hands back its import.
 *
 * @param  list<list<string>>  $rows
 * @param  list<array{column: string, target: string}>  $mapping
 */
function importOnDisk(Business $business, array $rows, array $mapping, string $name = 'inventario.xlsx'): ProductImport
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);

    $tmp = tempnam(sys_get_temp_dir(), 'import-job').'.xlsx';
    new Xlsx($spreadsheet)->save($tmp);

    $path = 'imports/business-'.$business->id.'/'.$name;
    Storage::disk('local')->put($path, (string) file_get_contents($tmp));

    return $business->productImports()->create([
        'original_name' => $name,
        'path' => $path,
        'mapping' => $mapping,
        'total_rows' => count($rows) - 1,
        'status' => 'pending',
    ]);
}

test('the job turns the mapped core into products and every column into knowledge', function (): void {
    Storage::fake('local');
    Embeddings::fake();

    $business = Business::factory()->create();

    $import = importOnDisk($business, [
        ['Estudio', 'Precio', 'Preparación'],
        ['Ecodoppler', '$ 15.000,50', 'Venir en ayunas'],
        ['Radiografía', '8000', ''],
    ], [
        ['column' => 'Estudio', 'target' => 'name'],
        ['column' => 'Precio', 'target' => 'price'],
        ['column' => 'Preparación', 'target' => 'extra', 'label' => 'Preparación del estudio'],
    ]);

    new ProcessProductImport($import->id)->handle(app(ImportFileReader::class));

    expect($business->products()->pluck('name')->all())->toBe(['Ecodoppler', 'Radiografía'])
        ->and($business->products()->where('name', 'Ecodoppler')->first())
        ->price->toBe('15000.50')
        ->and($import->fresh()->status)->toBe('done');

    $document = KnowledgeDocument::query()->sole();

    expect($document->business_id)->toBe($business->id)
        ->and($document->source_type)->toBe('import')
        ->and($document->content)->toContain('Preparación del estudio: Venir en ayunas');
});

test('re-importing the same file replaces its knowledge instead of duplicating it', function (): void {
    Storage::fake('local');
    Embeddings::fake();

    $business = Business::factory()->create();
    $mapping = [['column' => 'Producto', 'target' => 'name']];

    $first = importOnDisk($business, [['Producto'], ['Pan de campo']], $mapping);
    new ProcessProductImport($first->id)->handle(app(ImportFileReader::class));

    $second = importOnDisk($business, [['Producto'], ['Pan integral']], $mapping);
    new ProcessProductImport($second->id)->handle(app(ImportFileReader::class));

    expect(KnowledgeDocument::query()->count())->toBe(1)
        ->and(KnowledgeDocument::query()->sole()->content)->toContain('Pan integral');
});

test('an import never deletes what the business already loaded', function (): void {
    Storage::fake('local');
    Embeddings::fake();

    $business = Business::factory()->create();
    $business->products()->create(['name' => 'Factura']);

    $import = importOnDisk($business, [['Producto'], ['Pan de campo']], [['column' => 'Producto', 'target' => 'name']]);
    new ProcessProductImport($import->id)->handle(app(ImportFileReader::class));

    expect($business->products()->pluck('name')->sort()->values()->all())->toBe(['Factura', 'Pan de campo']);
});

test('with every column steered to extra there are no products, but the knowledge stays', function (): void {
    Storage::fake('local');
    Embeddings::fake();

    $business = Business::factory()->create();

    $import = importOnDisk($business, [['Dato'], ['Algo importante']], [['column' => 'Dato', 'target' => 'extra']]);
    new ProcessProductImport($import->id)->handle(app(ImportFileReader::class));

    expect($business->products()->count())->toBe(0)
        ->and(KnowledgeDocument::query()->sole()->content)->toContain('Algo importante')
        ->and($import->fresh()->status)->toBe('done');
});

test('a file gone missing marks the import failed and rethrows for the retry', function (): void {
    Storage::fake('local');

    $business = Business::factory()->create();

    $import = $business->productImports()->create([
        'original_name' => 'fantasma.xlsx',
        'path' => 'imports/nowhere.xlsx',
        'mapping' => [['column' => 'Producto', 'target' => 'name']],
        'total_rows' => 1,
        'status' => 'pending',
    ]);

    expect(fn () => new ProcessProductImport($import->id)->handle(app(ImportFileReader::class)))
        ->toThrow(Exception::class)
        ->and($import->fresh()->status)->toBe('failed');
});
