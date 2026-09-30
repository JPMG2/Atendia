<?php

declare(strict_types=1);

use App\Actions\Business\SaveAssistantFaq;
use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The team teaches, the owner is TOLD — never asked
|--------------------------------------------------------------------------
| An approval queue sits on the busiest person in the business, so the
| answer goes live the moment the agent writes it and the bell carries the
| news. Nothing waits on the owner opening the panel.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Embeddings::fake();

    $this->business = Business::factory()->create();
});

/** A user of this business wearing one of the two client roles. */
function teamMember(Business $business, string $role, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->business()->associate($business)->save();
    // syncRoles, not assignRole: the factory already hands out `client`,
    // and an agent carrying both would pass the owner's permission.
    $user->syncRoles([$role]);

    return $user;
}

test('an agent teaching leaves the owner a bell row, and the answer is already live', function (): void {
    $agent = teamMember($this->business, 'agent', 'Sofía Ramírez');
    $this->actingAs($agent);

    $document = app(SaveAssistantFaq::class)->handle($this->business, [
        'question' => '¿Atienden los sábados?',
        'answer' => 'Sí, de 9 a 13.',
    ]);

    $notice = PanelNotification::query()->where('business_id', $this->business->id)->sole();

    expect($document->exists)->toBeTrue()
        ->and($notice->type)->toBe(PanelNotificationType::TaughtByTeammate)
        ->and($notice->payload['who'])->toBe('Sofía Ramírez')
        ->and($notice->payload['question'])->toBe('¿Atienden los sábados?');
});

test('the owner teaching her own assistant tells nobody', function (): void {
    $this->actingAs(teamMember($this->business, 'client', 'María González'));

    app(SaveAssistantFaq::class)->handle($this->business, [
        'question' => '¿Atienden los sábados?',
        'answer' => 'Sí, de 9 a 13.',
    ]);

    expect(PanelNotification::query()->where('business_id', $this->business->id)->count())->toBe(0);
});

test('teaching the same answer twice is one piece of news', function (): void {
    $this->actingAs(teamMember($this->business, 'agent', 'Sofía Ramírez'));

    $document = app(SaveAssistantFaq::class)->handle($this->business, [
        'question' => '¿Atienden los sábados?',
        'answer' => 'Sí, de 9 a 13.',
    ]);

    app(SaveAssistantFaq::class)->handle($this->business, [
        'question' => '¿Atienden los sábados?',
        'answer' => 'Sí, de 9 a 14.',
    ], $document->id);

    expect(PanelNotification::query()->where('business_id', $this->business->id)->count())->toBe(1);
});
