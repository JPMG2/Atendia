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
        // In v5 the changes live in `attribute_changes` and NOT in `properties`,
        // which is now for extra data added by hand. Reading the wrong column makes
        // the audit look like it stores nothing.
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
