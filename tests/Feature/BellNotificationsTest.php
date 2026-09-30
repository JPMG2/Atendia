<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Events\PanelNotificationRaised;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\HandedToTeam;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\PanelNotification;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The bell's inbox — the notice belongs to the business, the read to the person
|--------------------------------------------------------------------------
| The dot that was always lit is gone: the badge counts only what THIS
| person has not opened. A shared read mark would hide the news from
| whoever opens the panel second, which is what these tests pin.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

/** A client user with a business, ready to read their own panel. */
function bellUser(?Business $business = null): User
{
    $user = User::factory()->create();
    $user->business()->associate($business ?? Business::factory()->create())->save();

    return $user;
}

test('the bell stays dark when there is nothing to read', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Livewire::actingAs(bellUser())
        ->test('client.bell')
        ->assertSet('unread', 0)
        ->assertDontSeeHtml('data-testid="bell-count"');
});

test('the badge counts what this person has not opened', function (): void {
    $user = bellUser();
    PanelNotification::factory()->count(3)->for($user->business)->create();

    expect(PanelNotification::unreadCountFor($user))->toBe(3);
});

test('a teammate reading a notice does not clear it for the rest', function (): void {
    $business = Business::factory()->create();
    $owner = bellUser($business);
    $agent = bellUser($business);

    $notice = PanelNotification::factory()->for($business)->create();
    $notice->markReadBy($agent);

    expect(PanelNotification::unreadCountFor($agent))->toBe(0)
        ->and(PanelNotification::unreadCountFor($owner))->toBe(1);
});

test('the same fact revives its row instead of stacking a second one', function (): void {
    $business = Business::factory()->create();

    PanelNotification::raise($business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 10]);
    PanelNotification::raise($business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 40]);

    $notices = PanelNotification::query()->where('business_id', $business->id)->get();

    expect($notices)->toHaveCount(1)
        ->and($notices->first()->payload['minutes'])->toBe(40);
});

test('a revived notice comes back unread for someone who had read it', function (): void {
    $user = bellUser();
    $notice = PanelNotification::raise($user->business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 10]);
    $notice->markReadBy($user);

    PanelNotification::raise($user->business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 40]);

    expect(PanelNotification::unreadCountFor($user))->toBe(1);
});

test('opening a row marks it read and drops the badge', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();
    $notice = PanelNotification::factory()->for($user->business)->create();

    Livewire::actingAs($user)
        ->test('client.bell')
        ->assertSet('unread', 1)
        ->call('read', [$notice->id])
        ->assertSet('unread', 0);
});

test('marking all as read empties the badge in one move', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();
    PanelNotification::factory()->count(4)->for($user->business)->create();

    Livewire::actingAs($user)
        ->test('client.bell')
        ->call('readAll')
        ->assertSet('unread', 0);
});

test('the panel writes each line from its own payload', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();
    PanelNotification::factory()->for($user->business)->create(['payload' => ['name' => 'Carla Ruiz', 'minutes' => 25]]);

    Livewire::actingAs($user)
        ->test('client.bell')
        ->call('open')
        ->assertSee('Carla Ruiz espera respuesta desde hace 25 minutos');
});

test('a business never reads another business notices', function (): void {
    $mine = bellUser();
    $theirs = Business::factory()->create();
    PanelNotification::factory()->for($theirs)->create();

    app(Tenant::class)->for($mine->business_id, function () use ($mine): void {
        expect(PanelNotification::unreadCountFor($mine))->toBe(0);
    });
});

test('an escalation leaves the team a row in the bell', function (): void {
    $user = bellUser();
    $thread = Conversation::factory()->for($user->business)->create(['contact_name' => 'Carla Ruiz']);

    (new Panel($thread, [], HandedToTeam::class))->send();

    $notice = PanelNotification::query()->where('business_id', $user->business_id)->sole();

    expect($notice->type)->toBe(PanelNotificationType::HandedToTeam)
        ->and($notice->payload['name'])->toBe('Carla Ruiz')
        ->and($notice->url)->toContain('hilo='.$thread->id);
});

test('three of the same kind collapse into one counted row', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();

    foreach (['Carla', 'Diego', 'Ana'] as $index => $name) {
        PanelNotification::factory()->for($user->business)->create([
            'dedupe_key' => 'waiting:'.$index,
            'payload' => ['name' => $name, 'minutes' => 10],
        ]);
    }

    Livewire::actingAs($user)
        ->test('client.bell')
        ->call('open')
        ->assertSee('3 clientes esperan respuesta')
        ->assertDontSee('Carla espera respuesta desde hace 10 minutos');
});

test('two of the same kind still say who is waiting', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();

    foreach (['Carla', 'Diego'] as $index => $name) {
        PanelNotification::factory()->for($user->business)->create([
            'dedupe_key' => 'waiting:'.$index,
            'payload' => ['name' => $name, 'minutes' => 10],
        ]);
    }

    Livewire::actingAs($user)
        ->test('client.bell')
        ->call('open')
        ->assertSee('Carla espera respuesta desde hace 10 minutos')
        ->assertSee('Diego espera respuesta desde hace 10 minutos');
});

test('a sweep repeating an unchanged fact does not keep it unread', function (): void {
    $user = bellUser();
    $notice = PanelNotification::raise($user->business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 10], null, revive: false);
    $notice->markReadBy($user);

    PanelNotification::raise($user->business, PanelNotificationType::CustomerWaiting, 'waiting:7', ['name' => 'Carla', 'minutes' => 20], null, revive: false);

    expect(PanelNotification::unreadCountFor($user))->toBe(0);
});

test('a raised notice tells the socket to light the bell', function (): void {
    Event::fake([PanelNotificationRaised::class]);
    $business = Business::factory()->create();

    PanelNotification::raise($business, PanelNotificationType::CustomerWaiting, 'waiting:1', ['name' => 'Carla', 'minutes' => 5]);

    Event::assertDispatched(
        PanelNotificationRaised::class,
        fn (PanelNotificationRaised $event): bool => $event->businessId === (int) $business->id,
    );
});

test('a muted kind leaves this person alone and nobody else', function (): void {
    $business = Business::factory()->create();
    $owner = bellUser($business);
    $agent = bellUser($business);

    PanelNotification::factory()->for($business)->create();
    $owner->toggleBellType(PanelNotificationType::CustomerWaiting, false);

    expect(PanelNotification::unreadCountFor($owner->fresh()))->toBe(0)
        ->and(PanelNotification::unreadCountFor($agent))->toBe(1);
});

test('the settings switch mutes and unmutes one kind', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = bellUser();

    Livewire::actingAs($user)
        ->test('settings.section-bell')
        ->call('toggle', 'customer_waiting')
        ->call('toggle', 'appointment_booked');

    expect($user->fresh()->mutedBellTypes())->toBe(['customer_waiting', 'appointment_booked']);

    Livewire::actingAs($user->fresh())
        ->test('settings.section-bell')
        ->call('toggle', 'customer_waiting');

    expect($user->fresh()->mutedBellTypes())->toBe(['appointment_booked']);
});

test('notices past the window are pruned and fresh ones stay', function (): void {
    $business = Business::factory()->create();
    $keep = (int) config('atendia.bell.keep_days');

    $old = PanelNotification::factory()->for($business)->create();
    $old->forceFill(['created_at' => now()->subDays($keep + 1)])->save();
    $fresh = PanelNotification::factory()->for($business)->create();

    $this->artisan('model:prune', ['--model' => [PanelNotification::class]])->assertSuccessful();

    expect(PanelNotification::query()->pluck('id')->all())->toBe([$fresh->id]);
});

test('the panel refuses a message that is not a panel message', function (): void {
    $business = Business::factory()->create();

    expect(fn (): bool => (new Panel($business, [], Business::class))->send())
        ->toThrow(InvalidArgumentException::class);
});
