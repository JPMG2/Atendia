<?php

declare(strict_types=1);

use App\Actions\Team\RemoveTeamMember;
use App\Enums\ConversationStatus;
use App\Mail\TeamInvitationMail;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Department;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Equipo" over the API
|--------------------------------------------------------------------------
| The phone reads the web's Team piece and writes through its Actions: the
| seats, the rules of the person sheet and the owner-only door are the web's.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Mail::fake();
});

function asTeamApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function agentOf(Business $business, array $departments = []): User
{
    $agent = User::factory()->create(['business_id' => $business->id, 'whatsapp' => '584247673951']);
    $agent->syncRoles(['agent']);
    $agent->departments()->sync(collect($departments)->pluck('id'));

    return $agent->refresh();
}

describe('reading the team', function (): void {
    it('lists the people, the open offers and the seats the plan has used', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);
        $offer = TeamInvitation::factory()->create(['business_id' => $owner->business_id, 'name' => 'Lucía', 'email' => 'lucia@shop.test']);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');

        expect(collect($data['members'])->pluck('id')->all())->toBe([$owner->id, $agent->id])
            ->and(collect($data['members'])->firstWhere('id', $agent->id)['isAgent'])->toBeTrue()
            ->and($data['invitations'][0]['email'])->toBe('lucia@shop.test')
            ->and($data['invitations'][0]['id'])->toBe($offer->id)
            ->and($data['seats']['used'])->toBe($owner->business->teamSeatsUsed());
    });

    it('speaks in the web screen\'s own words, with the names and counts already in them', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);
        Conversation::factory()->count(2)->create(['business_id' => $owner->business_id, 'assigned_user_id' => $agent->id, 'status' => ConversationStatus::Team]);
        $offer = TeamInvitation::factory()->create(['business_id' => $owner->business_id, 'name' => 'Lucía']);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');
        $row = collect($data['members'])->firstWhere('id', $agent->id);

        expect($data['copy']['title'])->toBe(__('team.title'))
            ->and($data['copy']['people']['invite'])->toBe(__('team.people.invite'))
            ->and($data['copy']['invite']['send'])->toBe(__('team.invite.send'))
            ->and($row['role'])->toBe(__('team.roles.agent'))
            ->and($row['removeTitle'])->toBe(__('team.people.remove_title', ['name' => $agent->name]))
            ->and($row['removeMessage'])->toBe(__('team.people.remove_message', ['count' => 2]))
            ->and(collect($data['members'])->firstWhere('id', $owner->id)['isYou'])->toBeTrue()
            ->and($row['isYou'])->toBeFalse()
            ->and($data['invitations'][0]['label'])->toBe('Lucía')
            ->and($data['invitations'][0]['cancelTitle'])->toBe(__('team.people.cancel_title', ['name' => 'Lucía']))
            ->and($data['invitations'][0]['id'])->toBe($offer->id)
            ->and($data['invitations'][0]['role'])->toBe(__('team.roles.agent'));
    });

    it('hands the WhatsApp over as the web\'s two boxes, dial code and number', function (): void {
        Country::factory()->create(['phone_code' => '+58']);
        $owner = payingUser();
        $agent = agentOf($owner->business);
        $withoutNumber = User::factory()->create(['business_id' => $owner->business_id, 'whatsapp' => null]);
        $withoutNumber->syncRoles(['agent']);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');
        $phone = collect($data['members'])->firstWhere('id', $agent->id)['phone'];

        expect($phone)->toBe(['dial' => '58', 'number' => '4247673951'])
            ->and(collect($data['members'])->firstWhere('id', $withoutNumber->id)['phone']['number'])->toBe('')
            ->and($data)->toHaveKey('defaultDial');
    });

    it('says what unlocks the departments instead of listing them when the plan has none', function (): void {
        $owner = payingUser();
        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');

        expect($data['hasDepartments'])->toBe($owner->business->plan()->hasDepartments)
            ->and($data['locked'] === null)->toBe($data['hasDepartments'])
            ->and($data['hasDepartments'] || $data['locked']['body'] === __('team.locked.body'))->toBeTrue();
    });

    it('lists the departments with their hours and what waits in them when the plan has them', function (): void {
        $owner = payingUser();
        $owner->business->subscription->forceFill(['plan' => 'negocio', 'trial_ends_at' => now()->subDay()])->save();
        $sales = Department::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ventas']);
        $agent = agentOf($owner->business, [$sales]);
        Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $sales->id, 'status' => ConversationStatus::Team]);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');
        $row = $data['departments'][0];

        expect($data['hasDepartments'])->toBeTrue()
            ->and($data['locked'])->toBeNull()
            ->and($row['name'])->toBe('Ventas')
            ->and($row['hint'])->toBe($sales->routing_hint)
            ->and($row['hours'])->toBe(__('team.hours.business'))
            ->and($row['waitingLabel'])->toBe(trans_choice('team.departments.waiting', 1, ['count' => 1]))
            ->and(collect($data['members'])->firstWhere('id', $agent->id)['departmentIds'])->toBe([$sales->id]);
    });

    it('never shows the people or offers of another business', function (): void {
        $owner = payingUser();
        $other = payingUser();
        agentOf($other->business);
        TeamInvitation::factory()->create(['business_id' => $other->business_id]);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');

        expect($data['members'])->toHaveCount(1)->and($data['invitations'])->toBeEmpty();
    });

    it('is closed to an agent', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);

        asTeamApp($agent)->getJson('/api/v1/team')->assertForbidden();
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/team')->assertUnauthorized();
    });
});

describe('inviting someone', function (): void {
    it('sends the one-time link by the email channel and lowercases the address', function (): void {
        $owner = payingUser();
        $sales = Department::factory()->create(['business_id' => $owner->business_id]);

        asTeamApp($owner)->postJson('/api/v1/team/invitations', [
            'name' => 'Martín Ruiz',
            'email' => 'Martin@Shop.test',
            'whatsapp' => '+54 9 11 5555-0101',
            'department_ids' => [$sales->id],
        ])->assertCreated()->assertJsonPath('message', __('team.notify.invited', ['email' => 'martin@shop.test']));

        $invitation = TeamInvitation::query()->sole();

        expect($invitation->email)->toBe('martin@shop.test')
            ->and($invitation->whatsapp)->toBe('5491155550101')
            ->and(strlen($invitation->token_hash))->toBe(64);
        Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => $mail->hasTo('martin@shop.test'));
    });

    it('refuses an address that already has an account', function (): void {
        $owner = payingUser();
        User::factory()->create(['email' => 'taken@shop.test']);

        asTeamApp($owner)->postJson('/api/v1/team/invitations', ['email' => 'taken@shop.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        expect(TeamInvitation::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    });

    it('refuses a department of another business', function (): void {
        $owner = payingUser();
        $foreign = Department::factory()->create(['business_id' => payingUser()->business_id]);

        asTeamApp($owner)->postJson('/api/v1/team/invitations', ['email' => 'nuevo@shop.test', 'department_ids' => [$foreign->id]])
            ->assertUnprocessable();

        expect(TeamInvitation::query()->count())->toBe(0);
    });

    it('tells the plan is full instead of sending the offer', function (): void {
        $owner = payingUser();

        while ($owner->business->hasTeamSeatLeft()) {
            agentOf($owner->business);
        }

        asTeamApp($owner)->postJson('/api/v1/team/invitations', ['email' => 'nuevo@shop.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        expect(TeamInvitation::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    });
});

describe('the open offers', function (): void {
    it('resends one with a fresh link', function (): void {
        $owner = payingUser();
        $offer = TeamInvitation::factory()->create(['business_id' => $owner->business_id, 'email' => 'lucia@shop.test']);
        $before = $offer->token_hash;

        asTeamApp($owner)->postJson("/api/v1/team/invitations/{$offer->id}/resend")
            ->assertOk()->assertJsonPath('message', __('team.notify.resent'));

        expect($offer->refresh()->token_hash)->not->toBe($before);
        Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail): bool => $mail->hasTo('lucia@shop.test'));
    });

    it('cancels one so its link stops working', function (): void {
        $owner = payingUser();
        $offer = TeamInvitation::factory()->create(['business_id' => $owner->business_id]);

        asTeamApp($owner)->deleteJson("/api/v1/team/invitations/{$offer->id}")->assertOk();

        expect(TeamInvitation::query()->count())->toBe(0);
    });

    it('does not reach the offer of another business', function (): void {
        $owner = payingUser();
        $foreign = TeamInvitation::factory()->create(['business_id' => payingUser()->business_id]);

        asTeamApp($owner)->deleteJson("/api/v1/team/invitations/{$foreign->id}")->assertNotFound();
        asTeamApp($owner)->postJson("/api/v1/team/invitations/{$foreign->id}/resend")->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): bool => TeamInvitation::query()->whereKey($foreign->id)->exists()))->toBeTrue();
    });
});

describe('editing and removing a person', function (): void {
    it('changes only where the handoffs reach them and keeps the rest when the body is partial', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);

        asTeamApp($owner)->putJson("/api/v1/team/members/{$agent->id}", ['whatsapp' => '+58 424 000 1111', 'name' => 'Hacker', 'email' => 'x@y.test'])
            ->assertOk()->assertJsonPath('message', __('team.notify.member_saved'));

        $agent->refresh();

        expect($agent->whatsapp)->toBe('584240001111')
            ->and($agent->name)->not->toBe('Hacker')
            ->and($agent->email)->not->toBe('x@y.test');
    });

    it('never edits or removes the owner or someone of another business', function (): void {
        $owner = payingUser();
        $foreign = agentOf(payingUser()->business);

        asTeamApp($owner)->putJson("/api/v1/team/members/{$owner->id}", ['whatsapp' => '5491155550101'])->assertForbidden();
        asTeamApp($owner)->deleteJson("/api/v1/team/members/{$owner->id}")->assertForbidden();
        asTeamApp($owner)->putJson("/api/v1/team/members/{$foreign->id}", ['whatsapp' => '5491155550101'])->assertNotFound();
        asTeamApp($owner)->deleteJson("/api/v1/team/members/{$foreign->id}")->assertNotFound();

        expect(User::query()->whereKey($foreign->id)->exists())->toBeTrue();
    });

    it('kills the app token of the person removed', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);
        $agentToken = appInstallToken($agent);

        app('auth')->forgetGuards();
        test()->withToken($agentToken)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertOk();

        asTeamApp($owner)->deleteJson("/api/v1/team/members/{$agent->id}")->assertOk();

        app('auth')->forgetGuards();
        test()->withToken($agentToken)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('removes an agent and hands their open threads back', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id, 'assigned_user_id' => $agent->id]);

        asTeamApp($owner)->deleteJson("/api/v1/team/members/{$agent->id}")
            ->assertOk()->assertJsonPath('message', __('team.notify.removed'));

        expect(User::query()->whereKey($agent->id)->exists())->toBeFalse()
            ->and($thread->refresh()->assigned_user_id)->toBeNull();
    });
});

function ownerWithDepartments(): User
{
    $owner = payingUser();
    $owner->business->subscription->forceFill(['plan' => 'negocio', 'trial_ends_at' => now()->subDay()])->save();

    return $owner;
}

describe('the departments', function (): void {
    it('hands the sheet the web\'s week, Monday first, with each day\'s name and who answers', function (): void {
        $owner = ownerWithDepartments();
        $agent = agentOf($owner->business);
        $room = Department::factory()->create(['business_id' => $owner->business_id, 'hours' => ['2' => ['08:00', '12:30']]]);
        $room->users()->sync([$agent->id]);

        $data = asTeamApp($owner)->getJson('/api/v1/team')->assertOk()->json('data');
        $row = collect($data['departments'])->firstWhere('id', $room->id);

        expect(collect($row['week'])->pluck('day')->all())->toBe([1, 2, 3, 4, 5, 6, 0])
            ->and($row['usesBusinessHours'])->toBeFalse()
            ->and($row['userIds'])->toBe([$agent->id])
            ->and(collect($row['week'])->firstWhere('day', 2))->toMatchArray(['open' => true, 'opens' => '08:00', 'closes' => '12:30'])
            ->and(collect($row['week'])->firstWhere('day', 3)['open'])->toBeFalse()
            ->and($row['editTitle'])->toBe(__('team.departments.edit', ['name' => $room->name]))
            ->and($row['deleteTitle'])->toBe(__('team.department.delete_title', ['name' => $room->name]))
            ->and($data['copy']['department']['save'])->toBe(__('team.department.save'))
            ->and($data['copy']['hours']['use_business'])->toBe(__('team.hours.use_business'))
            ->and($data['copy']['departments']['new'])->toBe(__('team.departments.new'))
            ->and($data['departmentDefaults']['usesBusinessHours'])->toBeTrue()
            ->and(collect($data['departmentDefaults']['week'])->where('open', true)->pluck('day')->all())->toBe([1, 2, 3, 4, 5]);
    });

    it('creates one with its own week and who answers, as the web\'s sheet saves it', function (): void {
        $owner = ownerWithDepartments();
        $agent = agentOf($owner->business);

        asTeamApp($owner)->postJson('/api/v1/team/departments', [
            'name' => 'Ventas',
            'routing_hint' => 'Cuando quieran comprar o pedir un presupuesto.',
            'uses_business_hours' => false,
            'week' => collect(range(0, 6))->mapWithKeys(fn (int $day): array => [$day => match ($day) {
                1 => ['open' => true, 'opens' => '09:00', 'closes' => '13:00'],
                6 => ['open' => true, 'opens' => '10:00', 'closes' => '12:00'],
                default => ['open' => false, 'opens' => '09:00', 'closes' => '18:00'],
            }])->all(),
            'user_ids' => [$agent->id],
        ])->assertCreated()->assertJsonPath('message', __('team.notify.department_saved', ['name' => 'Ventas']));

        $room = Department::query()->sole();

        expect($room->hours)->toBe(['1' => ['09:00', '13:00'], '6' => ['10:00', '12:00']])
            ->and($room->users->pluck('id')->all())->toBe([$agent->id]);
    });

    it('follows the business\'s hours when the body says nothing about them', function (): void {
        $owner = ownerWithDepartments();

        asTeamApp($owner)->postJson('/api/v1/team/departments', ['name' => 'Pagos', 'routing_hint' => 'Cuando hablen de un pago o un comprobante.'])
            ->assertCreated();

        expect(Department::query()->sole()->usesBusinessHours())->toBeTrue();
    });

    it('refuses what the web\'s sheet refuses', function (array $body, string $field): void {
        $owner = ownerWithDepartments();
        Department::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ventas']);
        $foreign = agentOf(payingUser()->business);

        asTeamApp($owner)->postJson('/api/v1/team/departments', [
            'name' => 'Soporte',
            'routing_hint' => 'Cuando algo no funcione como debería.',
            ...$body,
            ...($field === 'user_ids.0' ? ['user_ids' => [$foreign->id]] : []),
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Department::query()->count())->toBe(1);
    })->with([
        'a name already taken' => [['name' => 'Ventas'], 'name'],
        'a hint too short to route by' => [['routing_hint' => 'corto'], 'routing_hint'],
        'a day that closes before it opens' => [['uses_business_hours' => false, 'week' => [1 => ['open' => true, 'opens' => '18:00', 'closes' => '09:00']]], 'week.1.closes'],
        'a day open without its hours' => [['uses_business_hours' => false, 'week' => [2 => ['open' => true, 'opens' => '', 'closes' => '']]], 'week.2.opens'],
        'a person of another business' => [[], 'user_ids.0'],
    ]);

    it('ignores a week that is not in use when the department follows the business hours', function (): void {
        $owner = ownerWithDepartments();

        asTeamApp($owner)->postJson('/api/v1/team/departments', [
            'name' => 'Pagos',
            'routing_hint' => 'Cuando hablen de un pago o un comprobante.',
            'uses_business_hours' => true,
            'week' => [1 => ['open' => true, 'opens' => '18:00', 'closes' => '09:00']],
        ])->assertCreated();

        expect(Department::query()->sole()->hours)->toBeNull();
    });

    it('is closed with the plan\'s own sentence when the plan has no departments', function (): void {
        $owner = payingUser();
        $owner->business->subscription->forceFill(['plan' => 'emprende', 'trial_ends_at' => now()->subDay()])->save();

        asTeamApp($owner)->postJson('/api/v1/team/departments', ['name' => 'Ventas', 'routing_hint' => 'Cuando quieran comprar algo.'])
            ->assertForbidden()->assertJsonPath('message', __('team.notify.plan_needed'));

        expect(Department::query()->count())->toBe(0);
    });

    it('changes only what the body carries and keeps the rest', function (): void {
        $owner = ownerWithDepartments();
        $agent = agentOf($owner->business);
        $room = Department::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ventas', 'routing_hint' => 'Cuando quieran comprar o pedir precios.', 'hours' => ['1' => ['09:00', '13:00'], '2' => ['09:00', '13:00']]]);
        $room->users()->sync([$agent->id]);

        asTeamApp($owner)->putJson("/api/v1/team/departments/{$room->id}", ['name' => 'Comercial', 'week' => [2 => ['opens' => '10:00']]])
            ->assertOk()->assertJsonPath('message', __('team.notify.department_saved', ['name' => 'Comercial']));

        $room->refresh();

        expect($room->name)->toBe('Comercial')
            ->and($room->routing_hint)->toBe('Cuando quieran comprar o pedir precios.')
            ->and($room->hours)->toBe(['1' => ['09:00', '13:00'], '2' => ['10:00', '13:00']])
            ->and($room->users->pluck('id')->all())->toBe([$agent->id]);
    });

    it('can clear who answers and go back to the business hours', function (): void {
        $owner = ownerWithDepartments();
        $agent = agentOf($owner->business);
        $room = Department::factory()->create(['business_id' => $owner->business_id, 'hours' => ['1' => ['09:00', '13:00']]]);
        $room->users()->sync([$agent->id]);

        asTeamApp($owner)->putJson("/api/v1/team/departments/{$room->id}", ['uses_business_hours' => true, 'user_ids' => []])->assertOk();

        expect($room->refresh()->hours)->toBeNull()->and($room->users()->count())->toBe(0);
    });

    it('deletes one and its threads fall back to the whole team', function (): void {
        $owner = ownerWithDepartments();
        $room = Department::factory()->create(['business_id' => $owner->business_id]);
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $room->id, 'status' => ConversationStatus::Team]);

        asTeamApp($owner)->deleteJson("/api/v1/team/departments/{$room->id}")
            ->assertOk()->assertJsonPath('message', __('team.notify.department_deleted'));

        expect(Department::query()->count())->toBe(0)->and($thread->refresh()->department_id)->toBeNull();
    });

    it('never reaches the department of another business', function (): void {
        $owner = ownerWithDepartments();
        $foreign = Department::factory()->create(['business_id' => payingUser()->business_id, 'name' => 'Ajeno']);

        asTeamApp($owner)->putJson("/api/v1/team/departments/{$foreign->id}", ['name' => 'Mío'])->assertNotFound();
        asTeamApp($owner)->deleteJson("/api/v1/team/departments/{$foreign->id}")->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): string => Department::query()->whereKey($foreign->id)->value('name')))->toBe('Ajeno');
    });

    it('is closed to an agent and to a call without a token', function (): void {
        $owner = ownerWithDepartments();
        $agent = agentOf($owner->business);
        $room = Department::factory()->create(['business_id' => $owner->business_id]);

        asTeamApp($agent)->postJson('/api/v1/team/departments', ['name' => 'Ventas', 'routing_hint' => 'Cuando quieran comprar algo.'])->assertForbidden();
        asTeamApp($agent)->putJson("/api/v1/team/departments/{$room->id}", ['name' => 'X1'])->assertForbidden();
        asTeamApp($agent)->deleteJson("/api/v1/team/departments/{$room->id}")->assertForbidden();

        expect(Department::query()->count())->toBe(1);
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/team/departments', [])->assertUnauthorized();
    });
});

describe('a body that is not what the sheet sends', function (): void {
    it('does not staff a department with someone already taken out of the team', function (): void {
        $owner = ownerWithDepartments();
        $gone = agentOf($owner->business);
        $room = Department::factory()->create(['business_id' => $owner->business_id]);
        app(RemoveTeamMember::class)->handle($gone);

        asTeamApp($owner)->putJson("/api/v1/team/departments/{$room->id}", ['user_ids' => [$gone->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
    });

    it('answers a department body made of lists and words with the sheet\'s own refusals, never a crash', function (): void {
        $owner = ownerWithDepartments();

        asTeamApp($owner)->postJson('/api/v1/team/departments', [
            'name' => ['x'], 'routing_hint' => ['y'], 'uses_business_hours' => 'false', 'user_ids' => 'todos',
            'week' => [1 => 'lunes', 2 => ['open' => 'true', 'opens' => ['09:00'], 'closes' => ['18:00']]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'routing_hint']);

        expect(Department::query()->count())->toBe(0);
    });

    it('reads "false" as false', function (): void {
        $owner = ownerWithDepartments();

        asTeamApp($owner)->postJson('/api/v1/team/departments', ['name' => 'Pagos', 'routing_hint' => 'Cuando hablen de un pago o un comprobante.', 'uses_business_hours' => 'false', 'week' => [1 => ['open' => 'false', 'opens' => '09:00', 'closes' => '13:00']]])
            ->assertCreated();

        expect(Department::query()->sole()->hours)->not->toBeNull()->and(Department::query()->sole()->hours)->not->toHaveKey('1');
    });

    it('invites with a name that is not text as if no name came', function (): void {
        $owner = payingUser();

        asTeamApp($owner)->postJson('/api/v1/team/invitations', ['email' => 'nuevo@shop.test', 'name' => ['x'], 'whatsapp' => ['1']])->assertCreated();

        expect(TeamInvitation::query()->sole()->name)->toBeNull();
    });
});

describe('a person taken out of the team', function (): void {
    it('cannot walk back in by signing in with their old password', function (): void {
        $owner = payingUser();
        $agent = agentOf($owner->business);

        app(RemoveTeamMember::class)->handle($agent);

        // The app's door and the web's door: both used to bring a closed account back.
        test()->withHeaders(apiDeviceHeaders('install-0000-bbbb-2222'))->postJson('/api/v1/login', ['email' => $agent->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
            ->assertUnprocessable();
        test()->post('/login', ['email' => $agent->email, 'password' => 'password'])->assertSessionHasErrors('email');

        expect(User::query()->whereKey($agent->id)->exists())->toBeFalse()
            ->and($agent->tokens()->count())->toBe(0);
    });

    it('still lets an owner who closed their own account come back by signing in', function (): void {
        $owner = payingUser();
        $owner->syncRoles(['client']);
        $owner->delete();

        test()->withHeaders(apiDeviceHeaders('install-0000-cccc-3333'))->postJson('/api/v1/login', ['email' => $owner->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
            ->assertOk();

        expect(User::query()->whereKey($owner->id)->exists())->toBeTrue();
    });
});
