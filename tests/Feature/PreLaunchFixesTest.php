<?php

declare(strict_types=1);

use App\Actions\Business\UnlinkBusinessWhatsApp;
use App\Actions\Team\InviteTeamMember;
use App\Classes\Main\Schedule;
use App\Classes\Main\Team;
use App\Livewire\Forms\Admin\AdminUserForm;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppLinkEvent;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| What the 2026-11-02 review found before going out
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('a service offered as a turn', function (): void {
    it('is switched on from the service sheet and then the agenda can book it', function (): void {
        Queue::fake();
        $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
        $this->actingAs($user);

        Livewire::test('goods.index')
            ->call('add')
            ->set('form.data.name', 'Consulta general')
            ->set('form.data.is_bookable', true)
            ->call('saveService')
            ->assertHasNoErrors();

        $service = $user->business->services()->sole();

        expect($service->is_bookable)->toBeTrue()
            ->and($user->business->bookableService($service->id)?->id)->toBe($service->id);
    });

    it('is off by default, so nothing is booked that the owner did not mark', function (): void {
        Queue::fake();
        $user = User::factory()->create(['business_id' => Business::factory()->create()->id])->refresh();
        $this->actingAs($user);

        Livewire::test('goods.index')->call('add')->set('form.data.name', 'Corte')->call('saveService')->assertHasNoErrors();

        expect($user->business->services()->sole()->is_bookable)->toBeFalse();
    });

    it('is switched from the app too, and the sheet says so in the web words', function (): void {
        Queue::fake();
        $owner = payingUser()->refresh();
        $service = app(Tenant::class)->for($owner->business_id, fn (): Service => Service::factory()->create(['business_id' => $owner->business_id, 'is_bookable' => false]));
        app('auth')->forgetGuards();
        $api = $this->withToken(appInstallToken($owner))->withHeaders(apiDeviceHeaders());

        $api->putJson("/api/v1/services/{$service->id}", ['is_bookable' => true])->assertOk();
        $sheet = $api->getJson("/api/v1/services/{$service->id}")->assertOk()->json('data');

        expect(app(Tenant::class)->for($owner->business_id, fn (): bool => $service->fresh()->is_bookable))->toBeTrue()
            ->and($sheet['sheet']['values']['is_bookable'])->toBeTrue()
            ->and(Arr::dot($sheet['copy']))->toContain(__('client.services.bookable'), __('client.services.bookable_help'));
    });
});

describe('shifts that run past midnight', function (): void {
    it('collide with the next morning when they really step on it', function (): void {
        $errors = Schedule::overlapErrors([1 => [['opens_at' => '22:00', 'closes_at' => '02:00']], 2 => [['opens_at' => '01:00', 'closes_at' => '05:00']]]);

        expect($errors)->toHaveKey('week.2.0.opens_at')
            ->and($errors['week.2.0.opens_at'])->toContain('lunes');
    });

    it('do not collide with their own morning, which is another night', function (): void {
        expect(Schedule::overlapErrors([1 => [['opens_at' => '01:00', 'closes_at' => '05:00'], ['opens_at' => '22:00', 'closes_at' => '02:00']]]))->toBe([]);
    });

    it('wrap from Sunday night into Monday morning', function (): void {
        $errors = Schedule::overlapErrors([1 => [['opens_at' => '01:00', 'closes_at' => '05:00']], 0 => [['opens_at' => '23:00', 'closes_at' => '03:00']]]);

        // Monday's 01:00–05:00 does not run past midnight: the sentence must not say it does.
        expect($errors)->toHaveKey('week.0.0.opens_at')
            ->and($errors['week.0.0.opens_at'])->toBe(__('client.business.hours.overlaps_day', ['day' => 'lunes', 'shift' => '01:00–05:00']));
    });

    it('still accept touching ends and still catch a same-day overlap', function (): void {
        expect(Schedule::overlapErrors([1 => [['opens_at' => '09:00', 'closes_at' => '12:00'], ['opens_at' => '12:00', 'closes_at' => '18:00']]]))->toBe([])
            ->and(Schedule::overlapErrors([1 => [['opens_at' => '09:00', 'closes_at' => '13:00'], ['opens_at' => '12:00', 'closes_at' => '18:00']]]))
            ->toBe(['week.1.1.opens_at' => __('client.business.hours.overlaps', ['shift' => '09:00–13:00'])]);
    });
});

it('names a role made in the role editor by its own name when giving it to a person', function (): void {
    Role::findOrCreate('cobranza', 'web')->givePermissionTo('access-admin-panel');

    expect(AdminUserForm::assignableRoles())->toHaveKey('cobranza')
        ->and(AdminUserForm::assignableRoles()['cobranza'])->toBe('cobranza');
});

describe('unlinking the business WhatsApp', function (): void {
    function linkedOwner(): User
    {
        config()->set('services.evolution.url', 'http://evolution.test');
        $business = Business::factory()->create(['whatsapp_instance' => 'negocio-1', 'whatsapp_connected_at' => now()]);
        $owner = User::factory()->create(['business_id' => $business->id]);
        $owner->syncRoles('client');

        return $owner->refresh();
    }

    it('closes the session on the bridge and says the number is no longer linked', function (): void {
        $owner = linkedOwner();
        $state = 'open';
        Http::fake(function (HttpRequest $request) use (&$state) {
            if (str_contains($request->url(), '/instance/logout/')) {
                $state = 'close';

                return Http::response(['status' => 'SUCCESS']);
            }

            return Http::response(str_contains($request->url(), 'connectionState') ? ['instance' => ['state' => $state]] : []);
        });
        $this->actingAs($owner);

        livewire('whatsapp.link')
            ->assertSee(__('whatsapp.disconnect.button'))
            ->call('disconnect')
            ->assertDispatched('notify', fn (string $name, array $params): bool => $params['message'] === __('whatsapp.disconnect.done'));

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/instance/logout/negocio-1'));
        expect($owner->business->fresh()->whatsapp_connected_at)->toBeNull()
            ->and(WhatsAppLinkEvent::query()->latest('id')->value('reason'))->toBe(WhatsAppLinkEvent::OWNER_UNLINKED)
            // Done on purpose: the line's history does not count it as a fall.
            ->and(WhatsAppLinkEvent::reliability($owner->business->fresh())['outages'])->toBe(0);
    });

    it('is marked down before the logout, so the bridge closing it is not news', function (): void {
        $owner = linkedOwner();
        $seenDown = null;
        Http::fake(function (HttpRequest $request) use ($owner, &$seenDown) {
            if (str_contains($request->url(), '/instance/logout/')) {
                // What the "closed" webhook would find if it arrived right now.
                $seenDown = ! $owner->business->fresh()->isConnected();
            }

            return Http::response(['instance' => ['state' => 'close']]);
        });

        app(UnlinkBusinessWhatsApp::class)->handle($owner->business);

        expect($seenDown)->toBeTrue();
    });

    it('does not say it is unlinked when the bridge still has the phone', function (): void {
        $owner = linkedOwner();
        Http::fake(['*' => Http::response(['instance' => ['state' => 'open']])]);
        $this->actingAs($owner);

        livewire('whatsapp.link')
            ->call('disconnect')
            ->assertDispatched('notify', fn (string $name, array $params): bool => $params['message'] === __('whatsapp.disconnect.failed'));

        expect($owner->business->fresh()->whatsapp_connected_at)->not->toBeNull();
    });
});

describe('inviting someone to the team', function (): void {
    it('sends one mail for a double tap', function (): void {
        Mail::fake();
        $owner = payingUser()->refresh();
        $data = ['name' => 'Luis', 'email' => 'luis@shop.test', 'whatsapp' => null, 'department_ids' => []];

        app(InviteTeamMember::class)->handle($owner->business, $data, $owner);
        app(InviteTeamMember::class)->handle($owner->business, $data, $owner);

        Mail::assertQueuedCount(1);
    });

    it('sends one mail for a double tap on Reenviar, and a resend later still goes', function (): void {
        Mail::fake();
        $owner = payingUser()->refresh();
        app(InviteTeamMember::class)->handle($owner->business, ['name' => 'Luis', 'email' => 'luis@shop.test', 'whatsapp' => null, 'department_ids' => []], $owner);
        $invitation = $owner->business->teamInvitations()->sole();
        $team = new Team($owner->business);

        $this->travel(2)->minutes();
        $team->resend($invitation->id, $owner);
        $team->resend($invitation->id, $owner);
        Mail::assertQueuedCount(2);

        $this->travel(1)->minutes();
        $team->resend($invitation->id, $owner);
        Mail::assertQueuedCount(3);
    });

    it('stops at five mails a day to one address, resends included', function (): void {
        Mail::fake();
        $owner = payingUser()->refresh();
        $data = ['name' => 'Luis', 'email' => 'luis@shop.test', 'whatsapp' => null, 'department_ids' => []];

        foreach (range(1, InviteTeamMember::MAILS_PER_DAY) as $send) {
            app(InviteTeamMember::class)->handle($owner->business, $data, $owner);
            $this->travel(2)->minutes();
        }

        expect(InviteTeamMember::mailsLeft($owner->business, 'LUIS@shop.test'))->toBe(0);
        Mail::assertQueuedCount(InviteTeamMember::MAILS_PER_DAY);

        $invitation = $owner->business->teamInvitations()->sole();
        app('auth')->forgetGuards();
        $this->withToken(appInstallToken($owner))->withHeaders(apiDeviceHeaders())
            ->postJson("/api/v1/team/invitations/{$invitation->id}/resend")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('team.notify.too_many_mails'));

        Mail::assertQueuedCount(InviteTeamMember::MAILS_PER_DAY);
    });
});

it('locks an account tried from many addresses, not only from one', function (): void {
    Mail::fake();
    $user = payingUser(['email' => 'duena@shop.test'])->refresh();

    foreach (range(1, 25) as $try) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$try])->withHeaders(apiDeviceHeaders())
            ->postJson('/api/v1/login', ['email' => 'duena@shop.test', 'password' => 'wrong-'.$try, 'device_name' => 'Pixel']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeaders(apiDeviceHeaders())
        ->postJson('/api/v1/login', ['email' => 'duena@shop.test', 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', fn (string $message): bool => $message !== __('auth.failed'));
});

it('sends the app the words it used to copy from the web, in the caller variant', function (): void {
    $this->seed(MenuSeeder::class);

    $base = $this->getJson('/api/v1/brand')->assertOk()->json('data.copy');
    $voseo = $this->withHeaders(['Accept-Language' => 'es-AR'])->getJson('/api/v1/brand')->json('data.copy');

    expect($base)->toHaveKeys(['retry', 'loadMore', 'signOut', 'conversations'])
        ->and($base['loadMore'])->toBe(__('pagination.load_more'))
        ->and(count($base))->toBe(count(config('atendia.app.copy')))
        ->and($voseo['devicesSub'])->toBe(trans('profile.devices.sub', [], 'es_AR'));
});
