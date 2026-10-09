<?php

declare(strict_types=1);

use App\Enums\AdoptionStep;
use App\Models\AdoptionNudge;
use Database\Seeders\AdoptionNudgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the editor hands its rows to Alpine in the order of the ladder', function (): void {
    AdoptionNudge::factory()->create(['step' => AdoptionStep::CatalogLoaded]);
    $first = AdoptionNudge::factory()->create(['step' => AdoptionStep::Registered]);

    $rows = Livewire::test('catalog.adoption-nudge')->get('initialRows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['id'])->toBe($first->id)
        ->and($rows[0]['stepLabel'])->toBe('Se registró');
});

test('a message is created for a step, with its subject and its paragraphs', function (): void {
    Livewire::test('catalog.adoption-nudge')
        ->set('form.data.step', 'business')
        ->set('form.data.subject', '¿Te ayudamos con el catálogo?')
        ->set('form.data.body', "Hola {nombre},\n\nTe falta el catálogo de {negocio}.")
        ->call('create')
        ->assertHasNoErrors();

    $nudge = AdoptionNudge::query()->sole();

    expect($nudge->step)->toBe(AdoptionStep::BusinessCreated)
        ->and($nudge->body)->toBe("Hola {nombre},\n\nTe falta el catálogo de {negocio}.")
        ->and($nudge->is_active)->toBeTrue();
});

test('there is one message per step, and the last step has nobody to write to', function (): void {
    AdoptionNudge::factory()->create(['step' => AdoptionStep::Registered]);

    Livewire::test('catalog.adoption-nudge')
        ->set('form.data.step', 'registered')
        ->set('form.data.subject', 'Otro asunto')
        ->set('form.data.body', 'Un texto suficientemente largo')
        ->call('create')
        ->assertHasErrors('step');

    Livewire::test('catalog.adoption-nudge')
        ->set('form.data.step', 'answered')
        ->set('form.data.subject', 'Otro asunto')
        ->set('form.data.body', 'Un texto suficientemente largo')
        ->call('create')
        ->assertHasErrors('step');

    expect(AdoptionNudge::query()->count())->toBe(1);
});

test('the body has to say something and has to fit a link a mail client will open', function (): void {
    foreach (['ok', str_repeat('a', AdoptionNudge::BODY_MAX + 1)] as $body) {
        Livewire::test('catalog.adoption-nudge')
            ->set('form.data.step', 'catalog')
            ->set('form.data.subject', 'Asunto')
            ->set('form.data.body', $body)
            ->call('create')
            ->assertHasErrors('body');
    }

    expect(AdoptionNudge::query()->count())->toBe(0);
});

test('the seeder writes a message for every step with someone to write to and keeps her edits', function (): void {
    $this->seed(AdoptionNudgeSeeder::class);

    expect(AdoptionNudge::query()->count())->toBe(count(AdoptionNudge::stepOptions()));

    AdoptionNudge::query()->where('step', 'registered')->update(['subject' => 'Mi asunto']);
    $this->seed(AdoptionNudgeSeeder::class);

    expect(AdoptionNudge::query()->where('step', 'registered')->value('subject'))->toBe('Mi asunto');
});
