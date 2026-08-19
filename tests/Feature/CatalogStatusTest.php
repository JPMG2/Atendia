<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateCurrentStatus;
use App\Livewire\Forms\Catalog\CurrentStatusForm;
use App\Models\CurrentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.status')
        ->assertSet('form.currentStatusData.name', '')
        ->set('form.currentStatusData.name', 'En proceso')
        ->assertSet('form.currentStatusData.name', 'En proceso');
});

test('the status table hands its rows to Alpine so the search filters client-side', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    $html = Livewire::test('catalog.status')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($status->name);

    expect(railConfig($html, 'items')[0]['id'])->toBe($status->id);
});

test('the status editor renders its only input', function (): void {
    Livewire::test('catalog.status')->assertSee('Nombre');
});

test('the status master has no active flag, because the table has no such column', function (): void {
    // `current_statuses` only stores a name. Showing a state switch here would be
    // inventing a field the database does not keep.
    $html = Livewire::test('catalog.status')->html();

    expect($html)->not->toContain('is_active')
        ->and(railConfig($html, 'blank'))->toBe(['name' => '']);
});

/*
|--------------------------------------------------------------------------
| storeCurrentStatus — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a status is created', function (): void {
    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('create')
        ->assertHasNoErrors();

    expect(CurrentStatus::where('name', 'En proceso')->exists())->toBeTrue();
});

test('a duplicate name is caught as a field error, not as a database crash', function (): void {
    // `name` is UNIQUE and is the only data of this master: without the rule the
    // clash would be a Postgres error swallowed by tryAction.
    CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('create')
        ->assertHasErrors('name');

    expect(CurrentStatus::query()->count())->toBe(1);
});

test('a name with markup is rejected', function (): void {
    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', '<script>alert(1)</script>')
        ->call('create')
        ->assertHasErrors('name');
});

test('a name pasted with stray spaces is stored clean', function (): void {
    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', '  En   proceso  ')
        ->call('create')
        ->assertHasNoErrors();

    expect(CurrentStatus::query()->value('name'))->toBe('En proceso');
});

test('every attribute the action persists carries a validation rule', function (): void {
    $form = new CurrentStatusForm(
        new class extends Component
        {
            public function render(): string
            {
                return '<div></div>';
            }
        },
        'form',
    );
    $form->setup();

    $payload = (new ReflectionMethod($form, 'transformServiceData'))->invoke($form);
    $rules = (new ReflectionMethod($form, 'getValidationRules'))->invoke($form, null);

    expect(array_keys($payload))->toEqualCanonicalizing(array_keys($rules));
});

test('creating a status hands the refreshed rows back to Alpine', function (): void {
    CurrentStatus::factory()->create(['name' => 'Activo']);

    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Activo', 'En proceso'],
        );
});

test('the success toast names the entity in the masculine', function (): void {
    // "Estado" is masculine: the map has to say so, or the toast reads
    // "Estado creada correctamente".
    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Estado creado correctamente');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    $this->mock(
        CreateCurrentStatus::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.status')
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.currentStatusData.name', 'En proceso');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    $component = Livewire::test('catalog.status')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    $html = Livewire::test('catalog.status')->html();

    expect($html)->not->toContain('catalog.status.')
        ->not->toContain('catalog.common.')
        ->toContain(__('catalog.status.create'))
        ->toContain(__('catalog.status.empty'));
});

/*
|--------------------------------------------------------------------------
| Edición
|--------------------------------------------------------------------------
*/

test('opening a row loads that record into the form', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    $component = Livewire::test('catalog.status')->call('openEdit', $status->id);

    expect($component->get('form.currentStatusId'))->toBe($status->id)
        ->and($component->get('form.currentStatusData')->name)->toBe('En proceso');
});

test('editing a record updates it instead of creating a second one', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.currentStatusData.name', 'En curso')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(CurrentStatus::query()->count())->toBe(1)
        ->and($status->fresh()->name)->toBe('En curso');
});

test('keeping its own name while editing does not trip the unique rule', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.currentStatusData.name', 'En proceso')
        ->call('update')
        ->assertHasNoErrors();

    expect($status->fresh()->name)->toBe('En proceso');
});

test('taking a name that already belongs to another status is rejected', function (): void {
    CurrentStatus::factory()->create(['name' => 'Activo']);
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.currentStatusData.name', 'Activo')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($status->fresh()->name)->toBe('En proceso');
});

test('starting a new status clears the record left over from an edit', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    $component = Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->call('openCreate');

    expect($component->get('form.currentStatusId'))->toBeNull()
        ->and($component->get('form.currentStatusData')->name)->toBe('');
});

test('opening a status that no longer exists warns instead of blowing up', function (): void {
    Livewire::test('catalog.status')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    Livewire::test('catalog.status')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the masculine', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.currentStatusData.name', 'En curso')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Estado actualizado correctamente');
});
