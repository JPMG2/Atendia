<?php

declare(strict_types=1);

use App\Ai\Agents\ProductNameFixer;
use App\Jobs\ProcessProductImport;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ProductImport\ImportFileReader;
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
| "Tus productos" — the real screen
|--------------------------------------------------------------------------
| List, search by name or code, the availability switch and the editing
| sheet over the tenant's own rows, plus the shared spreadsheet import
| lane. ProductForm validates, the Inventory piece saves through the
| Actions, and nothing here is ever forced on the client.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // Saving the offer publishes knowledge, whose indexing would call the
    // embeddings API.
    Queue::fake();
});

/** A signed-in client whose business the screen reads and writes. */
function productsScreenUser(): User
{
    $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();

    test()->actingAs($user);

    return $user;
}

/**
 * @param  list<list<string>>  $rows
 */
function productsSheetFile(array $rows): File
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);

    $path = tempnam(sys_get_temp_dir(), 'products-import').'.xlsx';
    new Xlsx($spreadsheet)->save($path);

    return UploadedFile::fake()->createWithContent('repuestos.xlsx', (string) file_get_contents($path));
}

test('without products the screen leads with the excel drop', function (): void {
    productsScreenUser();

    Livewire::test('products.index')
        ->assertSee(__('client.products.empty_title'))
        ->assertSee(__('wizard.products.drop_title'))
        ->assertSee(__('client.products.empty_or'))
        ->assertSee(__('client.products.add'))
        // The find-and-operate toolbar belongs to a populated list only.
        ->assertDontSee(__('client.products.search_placeholder'));
});

test('the list shows code and price and the switch flips availability', function (): void {
    $user = productsScreenUser();
    $product = Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Alternador Fiat Palio', 'code' => 'ALT-021', 'price' => '185000']);

    Livewire::test('products.index')
        ->assertSee('Alternador Fiat Palio')
        ->assertSee('ALT-021')
        ->assertSee('185.000')
        ->assertDontSee(__('client.products.out_note'))
        ->call('toggle', $product->id)
        // Out of stock stays on the list: the assistant says so, never drops it.
        ->assertSee(__('client.products.out_note'));

    expect($product->refresh()->in_stock)->toBeFalse()
        ->and($product->is_active)->toBeTrue();
});

test('the search finds rows by name or code, accent-insensitively', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Bujía NGK', 'code' => 'NGK-6ES']);
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Radiador Clio', 'code' => 'RAD-330']);

    Livewire::test('products.index')
        ->set('search', 'bujia')
        ->assertSee('Bujía NGK')
        ->assertDontSee('Radiador Clio')
        ->assertSeeHtml('match-hit')
        ->set('search', 'rad-330')
        ->assertSee('Radiador Clio')
        ->assertDontSee('Bujía NGK')
        ->set('search', 'zzz')
        ->assertSee(__('client.products.no_results'));
});

test('the availability filter narrows the list to what is out of stock', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Correa de distribución', 'in_stock' => false]);
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Filtro de aceite']);

    Livewire::test('products.index')
        ->set('filter', 'out')
        ->assertSee('Correa de distribución')
        ->assertDontSee('Filtro de aceite');
});

test('the list pages with load more instead of rendering everything', function (): void {
    $user = productsScreenUser();

    foreach (range(1, 10) as $i) {
        Product::factory()->create(['business_id' => $user->business->id, 'name' => "Producto {$i}"]);
    }

    Livewire::test('products.index')
        ->assertSee('Producto 1')
        ->assertDontSee('Producto 9')
        ->assertSee(__('pagination.showing', ['shown' => 8, 'total' => 10]))
        ->call('loadMore')
        ->assertSee('Producto 9');
});

test('creating a product saves the tenant row with its code and price', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Ya existe']);

    Livewire::test('products.index')
        ->call('add')
        ->assertSet('sheetOpen', true)
        ->set('form.data.name', 'Batería 12x75')
        ->set('form.data.code', 'BAT-075')
        ->set('form.data.price', '145000')
        ->set('form.data.stock', '4')
        ->call('saveProduct')
        ->assertHasNoErrors()
        ->assertSet('sheetOpen', false);

    $product = $user->business->products()->where('name', 'Batería 12x75')->sole();

    expect($product->code)->toBe('BAT-075')
        ->and($product->price)->toBe('145000.00')
        ->and($product->stock)->toBe('4.00')
        ->and($product->in_stock)->toBeTrue();
});

test('editing a product loads the row into the sheet and updates it in place', function (): void {
    $user = productsScreenUser();
    $product = Product::factory()->create([
        'business_id' => $user->business->id,
        'name' => 'Radiador Clio',
        'price' => '98000.00',
    ]);

    Livewire::test('products.index')
        ->call('edit', $product->id)
        ->assertSee(__('client.products.sheet_title', ['name' => 'Radiador Clio']))
        // The decimal cast never leaks its trailing zeros into the input.
        ->assertSet('form.data.price', '98000')
        ->set('form.data.description', 'Con mangueras incluidas.')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->refresh()->description)->toBe('Con mangueras incluidas.');
});

test('the sheet validates without ever demanding price or stock', function (): void {
    productsScreenUser();

    Livewire::test('products.index')
        ->call('add')
        ->set('form.data.name', '')
        ->call('saveProduct')
        ->assertHasErrors(['name'])
        ->set('form.data.name', 'Bujía NGK')
        ->set('form.data.price', 'caro')
        ->call('saveProduct')
        ->assertHasErrors(['price'])
        // A bare name is the floor a product starts from.
        ->set('form.data.price', '')
        ->call('saveProduct')
        ->assertHasNoErrors();
});

test('a code is a per-business identity, trashed rows included', function (): void {
    $user = productsScreenUser();
    $holder = Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Alternador', 'code' => 'ALT-021']);
    $holder->delete();

    Livewire::test('products.index')
        ->call('add')
        ->set('form.data.name', 'Alternador Corsa')
        ->set('form.data.code', 'ALT-021')
        ->call('saveProduct')
        // The trashed holder still owns the code: a sync key never revives duplicated.
        ->assertHasErrors(['code'])
        ->set('form.data.code', 'ALT-022')
        ->call('saveProduct')
        ->assertHasNoErrors();
});

test('a duplicated name is rejected inside the business but free across tenants', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Bujía NGK']);
    Product::factory()->create(['business_id' => Business::factory()->create()->id, 'name' => 'Radiador Clio']);

    Livewire::test('products.index')
        ->call('add')
        ->set('form.data.name', 'Bujía NGK')
        ->call('saveProduct')
        ->assertHasErrors(['name'])
        ->set('form.data.name', 'Radiador Clio')
        ->call('saveProduct')
        ->assertHasNoErrors();
});

test('reusing a trashed name restores the old row instead of colliding', function (): void {
    $user = productsScreenUser();
    $product = Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Bujía NGK']);
    $product->delete();

    Livewire::test('products.index')
        ->call('add')
        ->set('form.data.name', 'Bujía NGK')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->refresh()->trashed())->toBeFalse()
        ->and($user->business->products()->count())->toBe(1);
});

test('the type picker only offers producto moulds and stores their values', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Pan de campo']);

    $goodsModality = ServiceModality::factory()->create(['code' => 'producto']);
    $goodsType = ServiceType::factory()->create(['name' => 'Producto de mostrador', 'service_modality_id' => $goodsModality->id, 'is_active' => true]);
    $serviceType = ServiceType::factory()->create(['name' => 'Consulta', 'is_active' => true]);

    $gluten = ServiceAttribute::factory()->create(['name' => 'Apto celíaco', 'data_type' => 'boolean', 'is_active' => true]);
    $goodsType->serviceAttributes()->attach($gluten->id, ['is_required' => false, 'sort_order' => 0]);

    $component = Livewire::test('products.index')
        ->call('add')
        ->assertSee(__('client.products.field_type'))
        ->set('form.data.name', 'Pan integral')
        // A mould from the services side never belongs on this screen.
        ->set('form.data.service_type_id', $serviceType->id)
        ->call('saveProduct')
        ->assertHasErrors(['service_type_id']);

    $component
        ->set('form.data.service_type_id', $goodsType->id)
        ->assertSee('Apto celíaco')
        ->set('form.data.attribute_values.'.$gluten->id, true)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = $user->business->products()->where('name', 'Pan integral')->sole();

    expect($product->service_type_id)->toBe($goodsType->id)
        ->and($product->attribute_values)->toBe([$gluten->id => true]);
});

test('closing the sheet never saves', function (): void {
    $user = productsScreenUser();
    $product = Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Bujía NGK']);

    Livewire::test('products.index')
        ->call('edit', $product->id)
        ->set('form.data.name', 'Otro nombre')
        ->call('closeSheet')
        ->assertSet('sheetOpen', false);

    expect($product->refresh()->name)->toBe('Bujía NGK');
});

test('the import lane runs the shared flow: upload, review, confirm, queue', function (): void {
    Queue::fake();
    Storage::fake('local');
    ProductNameFixer::fake([['corrections' => []]]);

    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Ya cargado']);

    Livewire::test('products.index')
        ->set('upload', productsSheetFile([
            ['Producto', 'Código', 'Precio'],
            ['Alternador Fiat Palio', 'ALT-021', '185000'],
        ]))
        // The mapper resolved every column deterministically, code included.
        ->assertSet('mapping', ['name', 'code', 'price'])
        ->assertSee(__('wizard.products.review_title'))
        ->call('confirmImport')
        ->assertSee(__('client.products.import_queued', ['file' => 'repuestos.xlsx', 'rows' => 1]));

    $import = ProductImport::query()->sole();

    expect($import->business_id)->toBe($user->business->id)
        ->and(collect($import->mapping)->pluck('target')->all())->toBe(['name', 'code', 'price']);

    Queue::assertPushed(ProcessProductImport::class);
});

test('the lane footnote reports the newest finished import', function (): void {
    $user = productsScreenUser();
    Product::factory()->create(['business_id' => $user->business->id, 'name' => 'Bujía NGK']);
    ProductImport::factory()->create([
        'business_id' => $user->business->id,
        'original_name' => 'repuestos-2026.xlsx',
        'total_rows' => 152,
        'status' => 'done',
    ]);

    Livewire::test('products.index')
        ->assertSee(__('client.products.import_last', ['file' => 'repuestos-2026.xlsx', 'rows' => 152]));
});

test('the import job writes the mapped code onto the product', function (): void {
    // The knowledge document the job feeds would queue real embeddings.
    Queue::fake();

    $user = productsScreenUser();
    Storage::fake('local');

    $file = productsSheetFile([
        ['Producto', 'Código', 'Precio'],
        ['Alternador Fiat Palio', 'ALT-021', '185000'],
        ['Bujía NGK', 'ALT-021', '5200'],
    ]);
    Storage::disk('local')->put('imports/test.xlsx', (string) file_get_contents($file->getRealPath()));

    $import = ProductImport::factory()->create([
        'business_id' => $user->business->id,
        'path' => 'imports/test.xlsx',
        'mapping' => [
            ['column' => 'Producto', 'label' => 'Producto', 'target' => 'name'],
            ['column' => 'Código', 'label' => 'Código', 'target' => 'code'],
            ['column' => 'Precio', 'label' => 'Precio', 'target' => 'price'],
        ],
        'status' => 'pending',
    ]);

    new ProcessProductImport($import->id)->handle(app(ImportFileReader::class));

    $first = Product::query()->where('name', 'Alternador Fiat Palio')->sole();
    $second = Product::query()->where('name', 'Bujía NGK')->sole();

    // The duplicated code stays with its first owner; the tolerant path
    // keeps the second row codeless instead of failing the import.
    expect($first->code)->toBe('ALT-021')
        ->and($second->code)->toBeNull()
        ->and($import->refresh()->status)->toBe('done');
});
