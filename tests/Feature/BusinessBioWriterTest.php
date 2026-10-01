<?php

declare(strict_types=1);

use App\Actions\Business\WriteBusinessBio;
use App\Ai\Agents\BusinessBioWriter;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Optimizar con IA" on the identity card
|--------------------------------------------------------------------------
| The banner promised a presentation writer that did not exist. It now writes
| one from what the business already told us, and leaves it in the field:
| nothing is stored until the owner saves.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
});

test('it drafts the presentation from the name, the trade and what is offered', function (): void {
    BusinessBioWriter::fake([['description' => 'Clínica Vida atiende odontología general.']]);

    Service::factory()->for($this->business)->create(['name' => 'Limpieza dental']);

    expect(app(WriteBusinessBio::class)->handle($this->business))
        ->toBe('Clínica Vida atiende odontología general.');
});

test('the name on screen wins over the stored one, so an unsaved rename still grounds it', function (): void {
    $seen = null;
    BusinessBioWriter::fake(function (string $prompt) use (&$seen): array {
        $seen = $prompt;

        return ['description' => 'Odontología Norte atiende a toda la familia.'];
    });

    app(WriteBusinessBio::class)->handle($this->business, 'Odontología Norte');

    expect($seen)->toContain('Odontología Norte');
});

test('a business with no name never reaches the model', function (): void {
    BusinessBioWriter::fake()->preventStrayPrompts();

    $business = Business::factory()->create(['name' => '']);

    expect(app(WriteBusinessBio::class)->handle($business))->toBeNull();
});

test('a model that never answers leaves the field untouched and says so', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    BusinessBioWriter::fake(fn () => throw new RuntimeException('provider down'));

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->set('form.description', 'Lo que escribió la dueña')
        ->call('writeBio')
        ->assertSet('form.description', 'Lo que escribió la dueña')
        // Unescaped: json_encode would turn "presentación" into an \u escape.
        ->assertDispatched('notify', fn (string $event, array $params): bool => str_contains(
            json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'No pudimos escribir la presentación',
        ));
});

test('the offer sits beside the field and turns into another take once it proposed', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    BusinessBioWriter::fake([['description' => 'Clínica Vida atiende odontología general.']]);

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->assertSeeHtml('data-testid="identity-bio-write"')
        ->assertSee(__('client.business.identity.ai_action'))
        ->assertDontSee(__('client.business.identity.ai_redo'))
        ->call('writeBio')
        ->assertSee(__('client.business.identity.ai_redo'));
});

test('the proposal lands in the field and nothing is stored until she saves', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    BusinessBioWriter::fake([['description' => 'Clínica Vida atiende odontología general.']]);

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    Livewire::actingAs($user)
        ->test('client.section-identity')
        ->call('writeBio')
        ->assertSet('form.description', 'Clínica Vida atiende odontología general.');

    expect($this->business->fresh()->description)->not->toBe('Clínica Vida atiende odontología general.');
});
