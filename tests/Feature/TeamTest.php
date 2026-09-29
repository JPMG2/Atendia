<?php

declare(strict_types=1);

use App\Actions\Account\CloseAccount;
use App\Classes\Main\Client;
use App\Enums\ConversationStatus;
use App\Mail\TeamInvitationMail;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

function teamOwner(): User
{
    return User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
}

function teamAgent(Business $business, array $departments = []): User
{
    $agent = User::factory()->create(['business_id' => $business->id]);
    $agent->syncRoles(['agent']);
    $agent->departments()->sync(collect($departments)->pluck('id'));

    return $agent->refresh();
}

test('the owner invites someone and the mail leaves through the email channel with a one-time link', function (): void {
    $owner = teamOwner();
    $sales = Department::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ventas']);
    $this->actingAs($owner);

    livewire('team.index')
        ->call('openMember')
        ->set('member.data.name', 'Martín Ruiz')
        ->set('member.data.email', 'Martin@Shop.test')
        ->set('member.data.whatsapp', '+54 9 11 5555-0101')
        ->set('member.data.department_ids', [$sales->id])
        ->call('saveMember')
        ->assertHasNoErrors()
        ->assertSet('memberOpen', false);

    $invitation = TeamInvitation::query()->sole();

    expect($invitation->email)->toBe('martin@shop.test')
        ->and($invitation->whatsapp)->toBe('5491155550101')
        ->and($invitation->department_ids)->toBe([$sales->id])
        ->and(strlen($invitation->token_hash))->toBe(64);
    Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => $mail->hasTo('martin@shop.test'));
});

test('an address that already has an account cannot be invited', function (): void {
    $owner = teamOwner();
    User::factory()->create(['email' => 'taken@shop.test']);
    $this->actingAs($owner);

    livewire('team.index')
        ->call('openMember')
        ->set('member.data.email', 'taken@shop.test')
        ->call('saveMember')
        ->assertHasErrors('email');

    expect(TeamInvitation::query()->count())->toBe(0);
});

test('accepting the link creates a verified agent of that business, in its departments, and signs them in', function (): void {
    $owner = teamOwner();
    $sales = Department::factory()->create(['business_id' => $owner->business_id]);
    $foreign = Department::factory()->create();
    $token = 'token-that-travels-only-in-the-mail';
    TeamInvitation::factory()->create([
        'business_id' => $owner->business_id,
        'email' => 'lucia@shop.test',
        'whatsapp' => '5491144440000',
        'department_ids' => [$sales->id, $foreign->id],
        'token_hash' => TeamInvitation::hashToken($token),
    ]);

    livewire('team.join', ['token' => $token])
        ->set('form.name', 'Lucía Paredes')
        ->set('form.password', 'Secreta-2026!')
        ->set('form.password_confirmation', 'Secreta-2026!')
        ->call('join')
        ->assertRedirect(route('conversations'));

    $agent = User::query()->where('email', 'lucia@shop.test')->sole();

    expect($agent->business_id)->toBe($owner->business_id)
        ->and($agent->isAgent())->toBeTrue()
        ->and($agent->email_verified_at)->not->toBeNull()
        ->and($agent->departments->pluck('id')->all())->toBe([$sales->id])
        ->and(TeamInvitation::query()->count())->toBe(0)
        ->and(Auth::id())->toBe($agent->id);
});

test('an expired or unknown link explains itself instead of a form', function (): void {
    TeamInvitation::factory()->create(['token_hash' => TeamInvitation::hashToken('old'), 'expires_at' => now()->subDay()]);

    $this->get(route('team.join', ['token' => 'old']))->assertOk()->assertSee(__('team.join.expired_title'));
    $this->get(route('team.join', ['token' => 'never']))->assertOk()->assertSee(__('team.join.expired_title'));
});

test('an agent works the inbox and never reaches the business setup', function (): void {
    $agent = teamAgent(teamOwner()->business);
    $this->actingAs($agent);

    $this->get(route('dashboard'))->assertRedirect(route('conversations'));
    $this->get(route('conversations'))->assertOk();
    $this->get(route('customers'))->assertOk();

    foreach (['my-business', 'team', 'my-plan', 'my-payments', 'my-services', 'assistant', 'statistics', 'whatsapp'] as $ownerOnly) {
        $this->get(route($ownerOnly))->assertForbidden();
    }
});

test('an agent sees only the threads of their departments or the ones they took', function (): void {
    $owner = teamOwner();
    [$sales, $payments] = Department::factory()->count(2)->sequence(['name' => 'Ventas'], ['name' => 'Pagos'])->create(['business_id' => $owner->business_id]);
    $agent = teamAgent($owner->business, [$sales]);

    $mine = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $sales->id]);
    $taken = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $payments->id, 'assigned_user_id' => $agent->id]);
    $theirs = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $payments->id]);

    expect(Client::for($agent)->inbox->threads->pluck('id')->sort()->values()->all())->toBe(collect([$mine->id, $taken->id])->sort()->values()->all())
        ->and(Client::for($agent)->inbox->thread($theirs->id))->toBeNull()
        ->and(Client::for($owner)->inbox->threads)->toHaveCount(3);
});

test('removing an agent sends their open threads back to the department and ends every session', function (): void {
    $owner = teamOwner();
    $agent = teamAgent($owner->business);
    $held = Conversation::factory()->create(['business_id' => $owner->business_id, 'assigned_user_id' => $agent->id, 'status' => ConversationStatus::Team]);
    $this->actingAs($owner);

    livewire('team.index')->call('remove', $agent->id);

    expect($held->refresh()->assigned_user_id)->toBeNull()
        ->and(User::query()->find($agent->id))->toBeNull();
});

test('the owner seat is never removed from the team screen', function (): void {
    $owner = teamOwner();
    $this->actingAs($owner);

    expect(fn () => Client::for($owner)->team->remove($owner->id))->toThrow(HttpException::class);
});

test('a department keeps its own hours and the week reads in one line', function (): void {
    $owner = teamOwner();
    $agent = teamAgent($owner->business);
    $this->actingAs($owner);

    livewire('team.index')
        ->call('openDepartment')
        ->set('department.data.name', 'Pagos')
        ->set('department.data.routing_hint', 'Reclama un cobro o manda un comprobante.')
        ->set('department.data.uses_business_hours', false)
        ->set('department.data.user_ids', [$agent->id])
        ->call('saveDepartment')
        ->assertHasNoErrors();

    $department = Department::query()->sole();

    expect($department->hours)->toHaveKeys(['1', '5'])
        ->and($department->hours)->not->toHaveKey('6')
        ->and($department->scheduleLabel())->toBe('lun a vie · 09:00–18:00')
        ->and($department->users->pluck('id')->all())->toBe([$agent->id]);
});

test('a closing time before the opening one is refused', function (): void {
    $this->actingAs(teamOwner());

    livewire('team.index')
        ->call('openDepartment')
        ->set('department.data.name', 'Pagos')
        ->set('department.data.routing_hint', 'Reclama un cobro o manda un comprobante.')
        ->set('department.data.uses_business_hours', false)
        ->set('department.data.week.1.closes', '08:00')
        ->call('saveDepartment')
        ->assertHasErrors('week.1.closes');
});

test('departments need the plan that brings them', function (): void {
    $owner = teamOwner();
    // Expired trial: the floor plan, which has no departments.
    $owner->business->subscription->update(['trial_ends_at' => now()->subDay()]);
    $this->actingAs($owner);

    livewire('team.index')
        ->assertSee(__('team.locked.title', ['plan' => __('plan.names.negocio')]))
        ->call('openDepartment')
        ->set('department.data.name', 'Pagos')
        ->set('department.data.routing_hint', 'Reclama un cobro o manda un comprobante.')
        ->call('saveDepartment')
        ->assertDispatched('notify', type: 'warning');

    expect(Department::query()->count())->toBe(0);
});

test('presence flips from the avatar menu', function (): void {
    $agent = teamAgent(teamOwner()->business);
    $this->actingAs($agent);

    livewire('team.presence')->call('toggle')->assertSet('available', false);

    expect($agent->refresh()->is_available)->toBeFalse();
});

test('an agent can never close the account, which would take the business with it', function (): void {
    $agent = teamAgent(teamOwner()->business);

    expect(fn () => app(CloseAccount::class)->handle($agent))->toThrow(HttpException::class)
        ->and($agent->business->refresh()->trashed())->toBeFalse();
});

test('an existing department opens its sheet with its own hours loaded', function (): void {
    $owner = teamOwner();
    $payments = Department::factory()->create(['business_id' => $owner->business_id, 'name' => 'Pagos', 'hours' => ['1' => ['09:00', '18:00']]]);
    $this->actingAs($owner);

    livewire('team.index')
        ->call('openDepartment', $payments->id)
        ->assertSet('departmentOpen', true)
        ->assertSet('department.data.uses_business_hours', false)
        ->assertSet('department.data.week.1.opens', '09:00')
        ->assertSee(__('team.hours.label'));
});

test('the plan seats count everybody in the panel and the owner holds the first one', function (): void {
    $owner = teamOwner();
    $business = $owner->business;

    // On trial the business sits on the plan that sells 2 seats, and the owner is one.
    expect($business->teamSeatsUsed())->toBe(1)
        ->and($business->hasTeamSeatLeft())->toBeTrue();

    teamAgent($business);

    expect($business->teamSeatsUsed())->toBe(2)
        ->and($business->hasTeamSeatLeft())->toBeFalse();

    $this->actingAs($owner);

    livewire('team.index')
        ->call('openMember')
        ->set('member.data.email', 'nuevo@shop.test')
        ->call('saveMember')
        ->assertDispatched('notify', type: 'warning');

    expect(TeamInvitation::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('the floor plan is the owner alone: it has no seat to offer', function (): void {
    $owner = teamOwner();
    // Expired trial: the floor plan sells a single seat, and the owner holds it.
    $owner->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    expect($owner->business->hasTeamSeatLeft())->toBeFalse();

    $this->actingAs($owner);

    livewire('team.index')
        ->call('openMember')
        ->set('member.data.email', 'nuevo@shop.test')
        ->call('saveMember')
        ->assertDispatched('notify', type: 'warning');

    expect(TeamInvitation::query()->count())->toBe(0);
});

test('an address that already holds an open offer is invited again with the seats full', function (): void {
    $owner = teamOwner();
    $business = $owner->business;
    TeamInvitation::factory()->create(['business_id' => $business->id, 'email' => 'ana@shop.test']);

    expect($business->hasTeamSeatLeft())->toBeFalse()
        ->and($business->canOfferSeatTo('ana@shop.test'))->toBeTrue();

    $this->actingAs($owner);

    livewire('team.index')
        ->call('openMember')
        ->set('member.data.email', 'ana@shop.test')
        ->call('saveMember')
        ->assertDispatched('notify', type: 'success');

    expect(TeamInvitation::query()->count())->toBe(1);
});

test('a plan that shrank under an offer already sent stops its link', function (): void {
    $owner = teamOwner();
    $business = $owner->business;
    $token = 'token-that-travels-only-in-the-mail';
    TeamInvitation::factory()->create([
        'business_id' => $business->id,
        'email' => 'lucia@shop.test',
        'token_hash' => TeamInvitation::hashToken($token),
    ]);

    // Expired trial: the floor plan sells a single seat, and the owner holds it.
    $business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->get(route('team.join', ['token' => $token]))->assertOk()->assertSee(__('team.join.seats_full', ['business' => $business->name]));

    livewire('team.join', ['token' => $token])
        ->set('form.name', 'Lucía Paredes')
        ->set('form.password', 'Secreta-2026!')
        ->set('form.password_confirmation', 'Secreta-2026!')
        ->call('join')
        ->assertNoRedirect();

    expect(User::query()->where('email', 'lucia@shop.test')->exists())->toBeFalse()
        ->and(TeamInvitation::query()->count())->toBe(1);
});

test('the freed seat comes back: removing an agent opens the door again', function (): void {
    $owner = teamOwner();
    $business = $owner->business;
    $agent = teamAgent($business);

    expect($business->hasTeamSeatLeft())->toBeFalse();

    Client::for($owner)->team->remove($agent->id);

    expect($business->hasTeamSeatLeft())->toBeTrue()
        ->and($business->teamSeatsUsed())->toBe(1);
});
