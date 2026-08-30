<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateCurrentStatus;
use App\Livewire\Forms\Catalog\CurrentStatusForm;
use App\Models\CurrentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    Livewire::test('catalog.status')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'En proceso')
        ->assertSet('form.data.name', 'En proceso');
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
    // `current_statuses` stores a name and a colour, nothing else. Showing a state
    // switch here would be inventing a field the database does not keep.
    $component = Livewire::test('catalog.status');

    // The DTO is the form's state: were there a status, it would be here.
    expect($component->html())->not->toContain('is_active')
        ->and($component->get('form.data')->toArray())
        ->toBe(['name' => '', 'color' => CurrentStatus::DEFAULT_COLOR]);
});

test('the colour of a status is a token key, never a hex', function (): void {
    // A stored hex renders the same in light and in dark, and several of them go
    // unreadable on the dark theme. A token key resolves through app.css, so the
    // same tag stays legible in both without a single extra line.
    foreach (CurrentStatus::COLORS as $color) {
        expect($color)->not->toStartWith('#')
            ->and($color)->toMatch('/^[a-z]+$/');
    }

    $status = CurrentStatus::factory()->create(['name' => 'En proceso', 'color' => 'info']);

    $rows = Livewire::test('catalog.status')->get('initialRows');

    expect($rows[0]['color'])->toBe('info');
});

test('the table paints each status with its own tag', function (): void {
    // The class is built at runtime from the stored key, so the colour never gets
    // written into the markup.
    expect(Livewire::test('catalog.status')->html())
        ->toContain(__('catalog.status.columns.color'))
        ->toContain('x-bind:class="\'is-\' + row.color"');
});

test('the colour palette offered by the form is the one the model allows', function (): void {
    // The key that gets saved, the one the CSS can paint and the one validation
    // accepts have to be the SAME list, or a status ends up with a transparent tag.
    $options = Livewire::test('catalog.status')->instance()->colorOptions;

    expect(collect($options)->pluck('value')->all())->toBe(CurrentStatus::COLORS);

    foreach ($options as $option) {
        expect($option['label'])->not->toContain('catalog.status.colors.');
    }
});

test('every colour of the palette can actually be painted by the stylesheet', function (): void {
    // The palette lives in PHP and the colours in app.css: if they drift, a
    // status saves and validates fine and renders as a transparent tag. Same for
    // the safelist, since the class is built at runtime.
    $css = File::get(resource_path('css/app.css'));
    $tailwind = File::get(base_path('tailwind.config.js'));

    foreach (CurrentStatus::COLORS as $color) {
        expect($css)->toContain(".status-tag.is-{$color}")
            ->and($tailwind)->toContain("'is-{$color}'");
    }
});

test('a colour outside the palette is rejected', function (): void {
    Livewire::test('catalog.status')
        ->set('form.data.name', 'En proceso')
        ->set('form.data.color', 'fucsia')
        ->call('create')
        ->assertHasErrors('color');

    expect(CurrentStatus::query()->count())->toBe(0);
});

test('a status keeps the colour it was saved with', function (): void {
    Livewire::test('catalog.status')
        ->set('form.data.name', 'Bloqueado')
        ->set('form.data.color', 'danger')
        ->call('create')
        ->assertHasNoErrors();

    expect(CurrentStatus::where('name', 'Bloqueado')->value('color'))->toBe('danger');
});

test('a new status starts on the neutral colour instead of no colour at all', function (): void {
    // The column is NOT NULL with a default; the DTO has to agree or the combobox
    // opens empty and the first save trips the validation for no reason.
    $component = Livewire::test('catalog.status');

    expect($component->get('form.data')->color)->toBe(CurrentStatus::DEFAULT_COLOR);
});

/*
|--------------------------------------------------------------------------
| storeCurrentStatus — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a status is created', function (): void {
    Livewire::test('catalog.status')
        ->set('form.data.name', 'En proceso')
        ->call('create')
        ->assertHasNoErrors();

    expect(CurrentStatus::where('name', 'En proceso')->exists())->toBeTrue();
});

test('a duplicate name is caught as a field error, not as a database crash', function (): void {
    // `name` is UNIQUE and is the only data of this master: without the rule the
    // clash would be a Postgres error swallowed by tryAction.
    CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->set('form.data.name', 'En proceso')
        ->call('create')
        ->assertHasErrors('name');

    expect(CurrentStatus::query()->count())->toBe(1);
});

test('a name with markup is rejected', function (): void {
    Livewire::test('catalog.status')
        ->set('form.data.name', '<script>alert(1)</script>')
        ->call('create')
        ->assertHasErrors('name');
});

test('a name pasted with stray spaces is stored clean', function (): void {
    Livewire::test('catalog.status')
        ->set('form.data.name', '  En   proceso  ')
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
        ->set('form.data.name', 'En proceso')
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
        ->set('form.data.name', 'En proceso')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Estado creado correctamente');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    $this->mock(
        CreateCurrentStatus::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.status')
        ->set('form.data.name', 'En proceso')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.name', 'En proceso');
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
    $status = CurrentStatus::factory()->create(['name' => 'En proceso', 'color' => 'info']);

    $component = Livewire::test('catalog.status')->call('openEdit', $status->id);

    expect($component->get('form.recordId'))->toBe($status->id)
        ->and($component->get('form.data')->name)->toBe('En proceso')
        ->and($component->get('form.data')->color)->toBe('info');
});

test('editing a record updates it instead of creating a second one', function (): void {
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.data.name', 'En curso')
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
        ->set('form.data.name', 'En proceso')
        ->call('update')
        ->assertHasNoErrors();

    expect($status->fresh()->name)->toBe('En proceso');
});

test('taking a name that already belongs to another status is rejected', function (): void {
    CurrentStatus::factory()->create(['name' => 'Activo']);
    $status = CurrentStatus::factory()->create(['name' => 'En proceso']);

    Livewire::test('catalog.status')
        ->call('openEdit', $status->id)
        ->set('form.data.name', 'Activo')
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

    expect($component->get('form.recordId'))->toBeNull()
        ->and($component->get('form.data')->name)->toBe('');
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
        ->set('form.data.name', 'En curso')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Estado actualizado correctamente');
});
