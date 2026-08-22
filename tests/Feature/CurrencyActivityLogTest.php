<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('editing a currency records who changed what', function (): void {
    $editor = User::factory()->create();
    $this->actingAs($editor);

    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino', 'symbol' => '$']);

    Livewire::test('catalog.currency')
        ->call('openEdit', $currency->id)
        ->set('form.data.symbol', 'AR$')
        ->call('update')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($editor->id)
        ->and($activity->subject_id)->toBe($currency->id)
        ->and($activity->log_name)->toBe('catalog')
        // OJO: en la v5 los cambios viven en `attribute_changes`, NO en `properties`,
        // que quedó para datos extra que uno agregue a mano. Mirar la columna
        // equivocada hace parecer que la auditoría no guarda nada.
        // logOnlyDirty: solo lo que cambió de verdad, no el registro entero.
        ->and($activity->attribute_changes['attributes'])->toBe(['symbol' => 'AR$'])
        ->and($activity->attribute_changes['old'])->toBe(['symbol' => '$']);
});

test('saving without touching anything logs nothing', function (): void {
    $this->actingAs(User::factory()->create());
    $currency = Currency::factory()->create(['code' => 'ARS', 'name' => 'Peso Argentino']);

    $before = Activity::query()->count();

    Livewire::test('catalog.currency')->call('openEdit', $currency->id)->call('update');

    expect(Activity::query()->count())->toBe($before);
});
