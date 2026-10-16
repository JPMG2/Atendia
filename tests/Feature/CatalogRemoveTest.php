<?php

declare(strict_types=1);

use App\Actions\Catalog\RemoveCatalogRecord;
use App\Models\Business;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DemoTag;
use App\Models\Province;
use App\Models\SeasonalWindow;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\ServiceType;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\WhatsappVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Deleting from a catalog master
|--------------------------------------------------------------------------
| The button used to just close the form. Records are soft-deletable, so the
| database never says "in use": each master declares who holds it, and these
| pin that a held record is explained and kept, and a free one is gone.
*/

test('a currency held by a business is explained, not deleted', function (): void {
    $currency = Currency::factory()->create(['code' => 'USD', 'name' => 'Dólar']);
    Business::factory()->create(['currency_id' => $currency->id]);

    $answer = Livewire::test('catalog.currency')->instance()->removalCheck($currency->id);

    expect($answer['status'])->toBe(RemoveCatalogRecord::IN_USE)
        ->and($answer['message'])->toContain('Dólar')->toContain('1 negocio');
    expect(Currency::query()->find($currency->id))->not->toBeNull();
});

test('a currency nobody holds is confirmed and then removed for good', function (): void {
    $currency = Currency::factory()->create(['code' => 'XXX', 'name' => 'Moneda de prueba']);

    $component = Livewire::test('catalog.currency');

    $answer = $component->instance()->removalCheck($currency->id);

    expect($answer['status'])->toBe(RemoveCatalogRecord::REMOVED)
        ->and($answer['accept'])->not->toBe('');

    $component->call('removeRecord', $currency->id)->assertDispatched('notify');

    // Gone, not hidden: its code is free to be used again.
    expect(Currency::withTrashed()->find($currency->id))->toBeNull();
    Currency::factory()->create(['code' => 'XXX', 'name' => 'Otra']);
});

test('a held record offers to be switched off from the same dialog', function (): void {
    $currency = Currency::factory()->create(['name' => 'Dólar', 'is_active' => true]);
    Business::factory()->create(['currency_id' => $currency->id]);

    $component = Livewire::test('catalog.currency');
    $answer = $component->instance()->removalCheck($currency->id);

    expect($answer['status'])->toBe(RemoveCatalogRecord::IN_USE)
        ->and($answer['deactivate'])->toBe('Desactivar');

    $component->call('deactivateRecord', $currency->id)->assertDispatched('notify');

    expect($currency->fresh()->is_active)->toBeFalse();

    // Already off: nothing left to offer but the explanation.
    expect(Livewire::test('catalog.currency')->instance()->removalCheck($currency->id))->not->toHaveKey('deactivate');
});

test('a holder that was soft-deleted still blocks, because it can come back', function (): void {
    $currency = Currency::factory()->create();
    $business = Business::factory()->create(['currency_id' => $currency->id]);
    $business->delete();

    $result = app(RemoveCatalogRecord::class)->handle($currency, $currency->id);

    expect($result)->toBe(RemoveCatalogRecord::IN_USE);
});

test('a country with provinces says how many and a province with none can go', function (): void {
    $country = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);

    expect(app(RemoveCatalogRecord::class)->inspect($country, $country->id)['usage'])->toBe(['provinces' => 1]);

    expect(app(RemoveCatalogRecord::class)->handle($province, $province->id))->toBe(RemoveCatalogRecord::REMOVED);
    expect(app(RemoveCatalogRecord::class)->handle($country, $country->id))->toBe(RemoveCatalogRecord::REMOVED);
});

test('a season takes its tags and variants with it and announces them', function (): void {
    $window = SeasonalWindow::factory()->create();
    DemoTag::factory()->create(['seasonal_window_id' => $window->id, 'slug' => 'navidad-1']);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $window->id]);

    $inspection = app(RemoveCatalogRecord::class)->inspect($window, $window->id);

    expect($inspection['status'])->toBe(RemoveCatalogRecord::REMOVED)
        ->and($inspection['owns'])->toBe(['demo_tags' => 1, 'whatsapp_variants' => 1]);

    app(RemoveCatalogRecord::class)->handle($window, $window->id);

    expect(SeasonalWindow::withTrashed()->count())->toBe(0)
        ->and(DemoTag::withTrashed()->count())->toBe(0)
        ->and(WhatsappVariant::withTrashed()->count())->toBe(0);
});

test('a deleted season stops being shown on the landing', function (): void {
    $window = SeasonalWindow::factory()->create([
        'is_active' => true,
        'starts_at' => SeasonalWindow::today()->subDay(),
        'ends_at' => SeasonalWindow::today()->addDay(),
    ]);

    expect(SeasonalWindow::inForce())->toHaveCount(1);

    app(RemoveCatalogRecord::class)->handle($window, $window->id);

    expect(SeasonalWindow::inForce())->toHaveCount(0);
});

test('a service type lets go of its attributes and is blocked while services use it', function (): void {
    $type = ServiceType::factory()->create();
    $attribute = ServiceAttribute::factory()->create();
    $type->serviceAttributes()->attach($attribute->id);

    expect(app(RemoveCatalogRecord::class)->inspect($type, $type->id)['owns'])->toBe(['service_attributes' => 1]);

    expect(app(RemoveCatalogRecord::class)->handle($type, $type->id))->toBe(RemoveCatalogRecord::REMOVED);
    // The attribute itself is somebody else's record: it stays.
    expect(ServiceAttribute::query()->find($attribute->id))->not->toBeNull();

    $held = ServiceType::factory()->create();
    Service::factory()->create(['service_type_id' => $held->id]);

    expect(app(RemoveCatalogRecord::class)->handle($held, $held->id))->toBe(RemoveCatalogRecord::IN_USE);
});

test('a ticket kind is held by tickets that name it by code', function (): void {
    SupportTicket::factory()->create(['kind' => 'problem']);
    $kind = SupportTicketKind::query()->where('code', 'problem')->firstOrFail();

    expect(app(RemoveCatalogRecord::class)->inspect($kind, $kind->id)['usage'])->toBe(['support_tickets' => 1]);
});

test('a master that cannot delete says so instead of failing', function (): void {
    $answer = Livewire::test('catalog.plan')->instance()->removalCheck(1);

    expect($answer['status'])->toBe(RemoveCatalogRecord::MISSING);
    expect(Livewire::test('catalog.plan')->instance()->removeRecord(1))->toBeFalse();
});

test('the delete button of a master is wired to a real action', function (): void {
    $js = file_get_contents(resource_path('js/catalog-master.js'));

    expect($js)->toContain('removalCheck')->toContain('removeRecord')
        ->not->toContain('Deleting is not wired');
});
