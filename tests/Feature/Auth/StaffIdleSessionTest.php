<?php

declare(strict_types=1);

use App\Http\Middleware\ExpireIdleStaffSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('atendia.security.staff_idle_minutes', 20);
});

test('an admin session ends after the idle limit and sends them to the login', function (): void {
    signInOnPanel('admin');

    $this->get(route('admin.dashboard'))->assertOk();

    $this->travel(21)->minutes();

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a real action keeps the admin session alive past the limit counted from the first visit', function (): void {
    signInOnPanel('admin');

    $this->get(route('admin.dashboard'))->assertOk();
    $this->travel(15)->minutes();
    $this->get(route('admin.dashboard'))->assertOk();
    $this->travel(15)->minutes();

    // 30 minutes after the first visit, but only 15 after the last one.
    $this->get(route('admin.dashboard'))->assertOk();
    $this->assertAuthenticated();
});

test('a client session is not cut short by the staff limit', function (): void {
    signInOnPanel('client');

    $this->get(route('dashboard'))->assertOk();
    $this->travel(3)->hours();

    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticated();
});

test('remember me revives a session only when the staff limit is off', function (int $limit, bool $revived): void {
    config()->set('atendia.security.staff_idle_minutes', $limit);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $login = $this->post('/login', ['email' => $admin->email, 'password' => 'password', 'remember' => 'on']);
    $recaller = $login->getCookie(auth()->guard('web')->getRecallerName(), false);

    // The session is gone; only the long-lived cookie is left.
    $this->flushSession();
    auth()->forgetGuards();

    $response = $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
        ->get(route('admin.dashboard'));

    // The limit-off case is the control: it proves the cookie really does
    // sign the user in, so the other case is not passing on an empty session.
    if ($revived) {
        $response->assertOk();
        $this->assertAuthenticated();
    } else {
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
})->with([
    'limit off: the cookie signs them in' => [0, true],
    'limit on: the cookie is refused' => [20, false],
]);

test('what the screen refreshes on its own does not count as activity', function (): void {
    $admin = signInOnPanel('admin');
    $session = app('session.store');
    $session->put('staff_last_seen_at', now()->subMinutes(10)->getTimestamp());

    $poll = Request::create('/livewire-x/update', 'POST', [
        'components' => [['updates' => [], 'calls' => [['method' => '$refresh', 'params' => []]]]],
    ], server: ['HTTP_X_LIVEWIRE' => '1']);
    $poll->setUserResolver(fn (): User => $admin);
    $poll->setLaravelSession($session);

    (new ExpireIdleStaffSession)->handle($poll, fn (): Response => new Response);

    // The clock stayed where it was: a tab left open would never idle out otherwise.
    expect($session->get('staff_last_seen_at'))->toBe(now()->subMinutes(10)->getTimestamp());

    $action = Request::create('/livewire-x/update', 'POST', [
        'components' => [['updates' => [], 'calls' => [['method' => 'save', 'params' => []]]]],
    ], server: ['HTTP_X_LIVEWIRE' => '1']);
    $action->setUserResolver(fn (): User => $admin);
    $action->setLaravelSession($session);

    (new ExpireIdleStaffSession)->handle($action, fn (): Response => new Response);

    expect($session->get('staff_last_seen_at'))->toBe(now()->getTimestamp());
});
