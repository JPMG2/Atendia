<?php

declare(strict_types=1);

use App\Actions\Business\SaveBusinessServices;
use App\Actions\Business\SyncOfferKnowledge;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The offer reaches the assistant
|--------------------------------------------------------------------------
| Hand-typed services and products — attribute values included — publish
| into the knowledge base the assistant searches, exactly like an imported
| sheet does. One document per side, replaced on change, gone when empty.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // Indexing a document would call the embeddings API.
    Queue::fake();
});

test('the services document tells price semantics, category and curated attributes', function (): void {
    $business = Business::factory()->create();
    $barba = ServiceCategory::factory()->create(['business_id' => $business->id, 'name' => 'Barba']);

    $type = ServiceType::factory()->create(['name' => 'Mesa']);
    $seats = ServiceAttribute::factory()->create(['name' => 'Personas', 'data_type' => 'number', 'unit' => 'personas', 'is_active' => true]);
    $type->serviceAttributes()->attach($seats->id, ['is_required' => false, 'sort_order' => 0, 'label_override' => 'Comensales']);

    Service::factory()->create([
        'business_id' => $business->id,
        'name' => 'Corte y barba',
        'service_category_id' => $barba->id,
        'service_type_id' => $type->id,
        'price' => '16500.00',
        'deposit' => '5000.00',
        'duration_minutes' => 45,
        'prep_note' => 'Llega con el pelo seco.',
        'attribute_values' => [$seats->id => '4'],
    ]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Consulta inicial', 'service_type_id' => null, 'price_type' => 'free']);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Pausado', 'service_type_id' => null, 'is_active' => false]);

    app(SyncOfferKnowledge::class)->handle($business, 'services');

    $document = KnowledgeDocument::query()->where('source_type', 'services')->sole();

    expect($document->content)
        ->toContain('Servicio: Corte y barba')
        ->toContain('Categoría: Barba')
        ->toContain('Precio: $ 16500')
        ->toContain('Adelanto para reservar: $ 5000')
        ->toContain('Duración: 45 minutos')
        ->toContain('Preparación previa: Llega con el pelo seco.')
        // The curated field speaks with THIS type's label.
        ->toContain('Comensales: 4 personas')
        ->toContain('Precio: gratis')
        // Paused means not offered: it never reaches an answer.
        ->not->toContain('Pausado');
});

test('the products document keeps out-of-stock rows listed and says so', function (): void {
    $business = Business::factory()->create();

    Product::factory()->create(['business_id' => $business->id, 'name' => 'Alternador', 'code' => 'ALT-021', 'price' => '185000.00']);
    Product::factory()->create(['business_id' => $business->id, 'name' => 'Correa', 'in_stock' => false]);

    app(SyncOfferKnowledge::class)->handle($business, 'products');

    $document = KnowledgeDocument::query()->where('source_type', 'products')->sole();

    expect($document->content)
        ->toContain('Producto: Alternador')
        ->toContain('Código: ALT-021')
        ->toContain('Precio: $ 185000')
        ->toContain('Correa')
        ->toContain('Disponibilidad: sin stock por ahora');
});

test('an emptied offer leaves the assistant memory too', function (): void {
    $business = Business::factory()->create();
    $service = Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte', 'service_type_id' => null]);

    app(SyncOfferKnowledge::class)->handle($business, 'services');
    expect(KnowledgeDocument::query()->where('source_type', 'services')->exists())->toBeTrue();

    $service->delete();
    app(SyncOfferKnowledge::class)->handle($business, 'services');

    expect(KnowledgeDocument::query()->where('source_type', 'services')->exists())->toBeFalse();
});

test('saving from the services sheet publishes the offer in the act', function (): void {
    $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
    $this->actingAs($user);

    Livewire::test('goods.index')
        ->call('add')
        ->set('form.data.name', 'Ecodoppler')
        ->set('form.data.price', '15000')
        ->call('saveService')
        ->assertHasNoErrors();

    expect(KnowledgeDocument::query()->where('source_type', 'services')->sole()->content)
        ->toContain('Servicio: Ecodoppler')
        ->toContain('Precio: $ 15000');
});

test('the row toggle republishes: a paused service leaves the answers', function (): void {
    $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
    $this->actingAs($user);
    $service = Service::factory()->create(['business_id' => $user->business->id, 'name' => 'Coloración', 'service_type_id' => null]);

    Livewire::test('goods.index')->call('toggle', $service->id);

    expect(KnowledgeDocument::query()->where('source_type', 'services')->exists())->toBeFalse();
});

test('the wizard list reconciler publishes the offer like the editor does', function (): void {
    $business = Business::factory()->create();

    app(SaveBusinessServices::class)->handle($business, ['Corte de dama', 'Mechas']);

    expect(KnowledgeDocument::query()->where('source_type', 'services')->sole()->content)
        ->toContain('Servicio: Corte de dama')
        ->toContain('Servicio: Mechas');
});
