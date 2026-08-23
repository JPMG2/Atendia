<?php

declare(strict_types=1);

use App\Models\ServiceModality;
use Database\Seeders\ServiceModalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it into a
    // real ServiceModalityDto. Without that, a wire:model update throws
    // "Cannot assign array to property" because Livewire cannot recurse into null.
    Livewire::test('catalog.service-modality')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Cita / Turno')
        ->assertSet('form.data.name', 'Cita / Turno');
});

test('the modality table hands its rows to Alpine so the search filters client-side', function (): void {
    $modality = ServiceModality::factory()->create(['name' => 'Cita / Turno']);

    $html = Livewire::test('catalog.service-modality')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($modality->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $modality = ServiceModality::factory()->create();

    $html = Livewire::test('catalog.service-modality')->html();

    expect($html)->toContain(':key="row.id"');

    expect(railConfig($html, 'items'))->toHaveCount(1)
        ->and(railConfig($html, 'items')[0]['id'])->toBe($modality->id);
});

test('the modality editor renders its real inputs', function (): void {
    Livewire::test('catalog.service-modality')
        ->assertSee('Clave')
        ->assertSee('Nombre')
        ->assertSee('Descripción')
        ->assertSee('Ícono')
        ->assertSee('Orden')
        ->assertSee('Estado');
});

test('the modalities are listed in the order the admin chose, not alphabetically', function (): void {
    // `sort_order` is the whole point of the field: it is what the business sees
    // when picking. Sorting by name here would make the column a lie.
    ServiceModality::factory()->create(['name' => 'Alquiler', 'code' => 'alquiler', 'sort_order' => 9]);
    ServiceModality::factory()->create(['name' => 'Cita', 'code' => 'cita', 'sort_order' => 1]);

    $rows = Livewire::test('catalog.service-modality')->get('initialRows');

    expect(collect($rows)->pluck('name')->all())->toBe(['Cita', 'Alquiler']);
});

test('a new modality is seeded as active and first in line', function (): void {
    $component = Livewire::test('catalog.service-modality');

    expect($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->sort_order)->toBe(0)
        ->and($component->get('form.data')->description)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| store — validation of every persisted attribute
|--------------------------------------------------------------------------
*/

test('a modality is created with all of its attributes', function (): void {
    Livewire::test('catalog.service-modality')
        ->set('form.data.code', 'cita')
        ->set('form.data.name', 'Cita / Turno')
        ->set('form.data.description', 'Fecha, hora y duración.')
        ->set('form.data.icon', 'calendar-check')
        ->set('form.data.sort_order', 3)
        ->set('form.data.is_active', true)
        ->call('create')
        ->assertHasNoErrors();

    $modality = ServiceModality::query()->firstWhere('code', 'cita');

    expect($modality)->not->toBeNull()
        ->and($modality->name)->toBe('Cita / Turno')
        ->and($modality->description)->toBe('Fecha, hora y duración.')
        ->and($modality->icon)->toBe('calendar-check')
        ->and($modality->sort_order)->toBe(3)
        ->and($modality->is_active)->toBeTrue();
});

test('the code is stored lowercase so it stays a stable hinge for the code', function (): void {
    Livewire::test('catalog.service-modality')
        ->set('form.data.code', '  RESERVA  ')
        ->set('form.data.name', 'Reserva')
        ->call('create')
        ->assertHasNoErrors();

    expect(ServiceModality::query()->firstWhere('code', 'reserva'))->not->toBeNull();
});

test('two modalities cannot share a code', function (): void {
    ServiceModality::factory()->create(['code' => 'cita']);

    Livewire::test('catalog.service-modality')
        ->set('form.data.code', 'cita')
        ->set('form.data.name', 'Otra cosa')
        ->call('create')
        ->assertHasErrors('code');

    expect(ServiceModality::query()->where('code', 'cita')->count())->toBe(1);
});

test('the icon has to be a real glyph, not free text', function (): void {
    // <x-icon> with a name that is not in config/icons.php paints a hole, so the
    // field is a closed list and not a free string.
    Livewire::test('catalog.service-modality')
        ->set('form.data.code', 'cita')
        ->set('form.data.name', 'Cita')
        ->set('form.data.icon', 'no-existe')
        ->call('create')
        ->assertHasErrors('icon');
});

test('the seeded modalities cover the behaviours, not the wording', function (): void {
    $this->seed(ServiceModalitySeeder::class);

    // Each seeded modality changes what the assistant ASKS and what the system
    // has to REMEMBER. "Consulta" and "Estudio" are not here on purpose: for the
    // system both are the same thing, an appointment. They are service TYPES.
    expect(ServiceModality::query()->pluck('code')->all())->toEqualCanonicalizing([
        'cita', 'clase', 'reserva', 'fila', 'producto', 'pedido',
        'encargo', 'presupuesto', 'alquiler', 'suscripcion', 'bono', 'donacion',
    ]);
});

test('seeding twice does not duplicate a modality', function (): void {
    $this->seed(ServiceModalitySeeder::class);
    $this->seed(ServiceModalitySeeder::class);

    expect(ServiceModality::query()->where('code', 'cita')->count())->toBe(1);
});
