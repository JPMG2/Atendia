<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the master mounts with an empty DTO so wire:model can bind into it', function (): void {
    Livewire::test('catalog.specialty')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Reumatología')
        ->assertSet('form.data.name', 'Reumatología');
});

test('the table hands its rows to Alpine, each carrying the name of its activity', function (): void {
    $activity = BusinessActivity::factory()->create(['code' => 'consultorio-medico', 'name' => 'Consultorio médico']);
    Specialty::factory()->for($activity, 'activity')->create(['code' => 'reumatologia', 'name' => 'Reumatología']);

    $component = Livewire::test('catalog.specialty');

    expect($component->html())->toContain('x-for="row in filtered()"')
        ->and($component->get('initialRows')[0]['activity'])->toBe('Consultorio médico');
});

test('the specialties are grouped by activity and ordered inside it, as the business will see them', function (): void {
    $first = BusinessActivity::factory()->create(['sort_order' => 1]);
    $second = BusinessActivity::factory()->create(['sort_order' => 2]);

    Specialty::factory()->for($second, 'activity')->create(['name' => 'Cocina china', 'sort_order' => 1]);
    Specialty::factory()->for($first, 'activity')->create(['name' => 'Neurología', 'sort_order' => 2]);
    Specialty::factory()->for($first, 'activity')->create(['name' => 'Alergología', 'sort_order' => 1]);

    $rows = Livewire::test('catalog.specialty')->get('initialRows');

    expect(collect($rows)->pluck('name')->all())->toBe(['Alergología', 'Neurología', 'Cocina china']);
});

test('creating one saves it under its activity and normalizes its code', function (): void {
    $activity = BusinessActivity::factory()->create();

    Livewire::test('catalog.specialty')
        ->set('form.data.business_activity_id', $activity->id)
        ->set('form.data.code', 'Medicina-Interna')
        ->set('form.data.name', 'Medicina interna')
        ->call('create')
        ->assertHasNoErrors();

    expect(Specialty::query()->sole()->code)->toBe('medicina-interna')
        ->and(Specialty::query()->sole()->business_activity_id)->toBe($activity->id);
});

test('the name is unique inside its activity but free in another one', function (): void {
    $medical = BusinessActivity::factory()->create();
    $restaurant = BusinessActivity::factory()->create();
    Specialty::factory()->for($medical, 'activity')->create(['code' => 'una', 'name' => 'Mediterránea']);

    Livewire::test('catalog.specialty')
        ->set('form.data.business_activity_id', $medical->id)
        ->set('form.data.code', 'otra')
        ->set('form.data.name', 'Mediterránea')
        ->call('create')
        ->assertHasErrors('name');

    Livewire::test('catalog.specialty')
        ->set('form.data.business_activity_id', $restaurant->id)
        ->set('form.data.code', 'otra')
        ->set('form.data.name', 'Mediterránea')
        ->call('create')
        ->assertHasNoErrors();

    expect(Specialty::query()->count())->toBe(2);
});

test('an activity and a unique code are required', function (): void {
    Specialty::factory()->create(['code' => 'repetida']);

    Livewire::test('catalog.specialty')
        ->set('form.data.code', 'repetida')
        ->set('form.data.name', 'Algo')
        ->call('create')
        ->assertHasErrors(['business_activity_id', 'code']);
});

test('editing keeps its own code and name without tripping the unique rules', function (): void {
    $specialty = Specialty::factory()->create(['code' => 'neurologia', 'name' => 'Neurología']);

    Livewire::test('catalog.specialty')
        ->call('openEdit', $specialty->id)
        ->assertSet('form.data.name', 'Neurología')
        ->set('form.data.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($specialty->fresh()->is_active)->toBeFalse();
});

test('the activity master saves the name of its specialty field, and empty means no field', function (): void {
    $activity = BusinessActivity::factory()->create(['name' => 'Consultorio médico']);

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.specialty_label', 'Especialidad')
        ->call('update')
        ->assertHasNoErrors();

    expect($activity->fresh()->specialty_label)->toBe('Especialidad');

    Livewire::test('catalog.business-activity')
        ->call('openEdit', $activity->id)
        ->set('form.data.specialty_label', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($activity->fresh()->specialty_label)->toBeNull();
});
