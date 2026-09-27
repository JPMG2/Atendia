<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\CatalogPhoto;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
| Load-more is NOT asserted here on purpose: wire:intersect auto-fires while
| the button sits inside the viewport, so on a tall window the list grows on
| its own (that IS the hybrid working). The paging logic lives in the
| feature test; the browser pins search, the sheet, and a clean console.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    // The screens show the business's real catalog (the mock-up data is long gone).
    $business = Business::factory()->create();
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte de caballero', 'is_active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Coloración', 'is_active' => true]);
    Product::factory()->create(['business_id' => $business->id, 'name' => 'Alternador Fiat Palio', 'is_active' => true]);
    Product::factory()->create(['business_id' => $business->id, 'name' => 'Bujía NGK BPR6ES', 'is_active' => true]);
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));
});

test('the services search narrows live and the sheet slides over and closes', function (): void {
    $page = visit('/servicios');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Corte de caballero')
        ->fill('list_search', 'coloracion')
        // The 300ms debounce plus the round-trip land before asserting.
        ->wait(1)
        ->assertSee('1 servicio')
        ->assertDontSee('Corte de caballero')
        ->fill('list_search', '')
        ->wait(1);

    $page->click('[aria-label="Editar Corte de caballero"]')
        ->assertSee('Ficha de Corte de caballero')
        ->assertSee('Preparación')
        ->screenshot(filename: 'services-sheet-open');

    // Cancel closes without saving; the list is still behind, untouched.
    $page->click('Cancelar')
        ->assertDontSee('Ficha de Corte de caballero')
        ->assertSee('Corte de caballero');
});

test('the products search matches the code in the real browser', function (): void {
    $page = visit('/productos');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Alternador Fiat Palio')
        ->fill('list_search', 'NGK')
        ->wait(1)
        ->assertSee('1 producto')
        ->assertDontSee('Alternador Fiat Palio')
        ->assertSee('Bujía NGK BPR6ES')
        ->screenshot(filename: 'products-search');
});

test('a product photo opens the same carousel, and zoom works inside it', function (): void {
    $product = Product::query()->where('name', 'Alternador Fiat Palio')->sole();

    // Real JPEGs on the public disk: the carousel must actually paint them.
    $photos = collect([[14, 164, 122], [255, 106, 77]])->map(function (array $rgb, int $at) use ($product): CatalogPhoto {
        $image = imagecreatetruecolor(640, 480);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        $path = "catalog/browser-{$at}.jpg";
        Storage::disk('public')->put($path, $bytes);
        Storage::disk('public')->put("catalog/browser-{$at}-thumb.jpg", $bytes);

        return CatalogPhoto::factory()->create([
            'business_id' => $product->business_id,
            'photoable_id' => $product->id,
            'path' => $path,
            'thumb_path' => "catalog/browser-{$at}-thumb.jpg",
            'sort_order' => $at,
        ]);
    });

    $page = visit('/productos');

    $page->click('[aria-label="Editar Alternador Fiat Palio"]')
        ->click('.cat-photo-open >> nth=1')
        ->assertVisible('.photo-viewer')
        ->assertSee('2 de 2')
        ->assertSee('100%')
        ->click('[aria-label="'.__('assistant.media.zoom_in').'"]')
        ->assertSee('200%')
        ->screenshot(filename: 'catalog-photo-viewer-zoomed')
        ->click('[aria-label="'.__('assistant.media.zoom_out').'"]')
        ->assertSee('100%')
        ->assertNoJavaScriptErrors();

    $photos->each(function (CatalogPhoto $photo): void {
        Storage::disk('public')->delete([$photo->path, $photo->thumb_path]);
    });
});
