<?php

declare(strict_types=1);

use App\Mail\AccountEmailChangeNotice;
use App\Mail\AccountEmailChangeRequested;
use App\Mail\AccountEmailUpdated;
use App\Models\User;
use App\Services\SignedLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Changing the login email (OWASP flow)
|--------------------------------------------------------------------------
| The request re-authenticates and parks the new address as PENDING: the
| new inbox gets a signed confirmation link, the current one a "wasn't me"
| notice. Only the link switches the address — and then the "still with
| you" mail lands in the new inbox.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

function requestEmailChange(string $email, string $password = 'password'): Testable
{
    return Livewire::test('settings.section-email')
        ->set('form.email', $email)
        ->set('form.current_password', $password)
        ->call('save');
}

function confirmUrlFor(User $user): string
{
    return SignedLink::temporary('settings.email.confirm', now()->addHour(), [
        'user' => $user->id,
        'hash' => sha1((string) $user->pending_email),
    ]);
}

test('a change request parks the address as pending and mails both inboxes', function (): void {
    $user = User::factory()->create(['email' => 'old@shop.test']);
    $this->actingAs($user->refresh());

    requestEmailChange('New@Shop.test')->assertHasNoErrors()->assertSee('new@shop.test');

    $user->refresh();

    expect($user->email)->toBe('old@shop.test')
        ->and($user->pending_email)->toBe('new@shop.test');

    Mail::assertQueued(AccountEmailChangeRequested::class, fn ($mail): bool => $mail->hasTo('new@shop.test'));
    Mail::assertQueued(AccountEmailChangeNotice::class, fn ($mail): bool => $mail->hasTo('old@shop.test')
        && $mail->newEmail === 'new@shop.test');
});

test('a change request demands the current password', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user->refresh());

    requestEmailChange('new@shop.test', 'wrong')->assertHasErrors('current_password');

    expect($user->refresh()->pending_email)->toBeNull();
    Mail::assertNothingQueued();
});

test('a change request refuses the same or a taken address', function (): void {
    User::factory()->create(['email' => 'taken@shop.test']);
    $user = User::factory()->create(['email' => 'mine@shop.test']);
    $this->actingAs($user->refresh());

    requestEmailChange('mine@shop.test')->assertHasErrors('email');
    requestEmailChange('taken@shop.test')->assertHasErrors('email');

    Mail::assertNothingQueued();
});

test('the signed link switches the address and sends the still-with-you mail', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'old@shop.test']);
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();
    DB::table('password_reset_tokens')->insert(['email' => 'old@shop.test', 'token' => 'x', 'created_at' => now()]);

    $this->get(confirmUrlFor($user))
        ->assertSuccessful()
        ->assertSee(__('settings.links.confirmed_title'));

    $user->refresh();

    expect($user->email)->toBe('new@shop.test')
        ->and($user->pending_email)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(DB::table('password_reset_tokens')->where('email', 'old@shop.test')->exists())->toBeFalse();

    Mail::assertQueued(AccountEmailUpdated::class, fn ($mail): bool => $mail->hasTo('new@shop.test'));
});

test('an unsigned or stale link changes nothing', function (): void {
    $user = User::factory()->create(['email' => 'old@shop.test']);
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();

    $this->get(route('settings.email.confirm', ['user' => $user->id, 'hash' => sha1('new@shop.test')]))
        ->assertForbidden();

    $stale = SignedLink::temporary('settings.email.confirm', now()->addHour(), [
        'user' => $user->id,
        'hash' => sha1('an-older-request@shop.test'),
    ]);

    $this->get($stale)->assertSuccessful()->assertSee(__('settings.links.invalid_title'));

    expect($user->refresh()->email)->toBe('old@shop.test');
    Mail::assertNothingQueued();
});

test('an address taken while pending is not switched', function (): void {
    $user = User::factory()->create(['email' => 'old@shop.test']);
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();
    $url = confirmUrlFor($user);
    User::factory()->create(['email' => 'new@shop.test']);

    $this->get($url)->assertSee(__('settings.links.invalid_title'));

    expect($user->refresh()->email)->toBe('old@shop.test')
        ->and($user->pending_email)->toBeNull();
});

test('the wasnt-me link from the old inbox cancels the pending change', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['pending_email' => 'thief@shop.test'])->save();

    $url = SignedLink::temporary('settings.email.cancel', now()->addDay(), [
        'user' => $user->id,
        'hash' => sha1('thief@shop.test'),
    ]);

    $this->get($url)->assertSuccessful()->assertSee(__('settings.links.cancelled_title'));

    expect($user->refresh()->pending_email)->toBeNull();
});

test('the card cancels a pending change and throttles resends', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();
    $this->actingAs($user->refresh());

    $component = Livewire::test('settings.section-email')->assertSee('new@shop.test');

    foreach (range(1, 4) as $ignored) {
        $component->call('resend');
    }

    Mail::assertQueued(AccountEmailChangeRequested::class, 3);

    $component->call('cancelChange')->assertDontSee(__('settings.email.resend'));

    expect($user->refresh()->pending_email)->toBeNull();
});

test('the confirmation mail carries a working signed link', function (): void {
    $user = User::factory()->create(['name' => 'Ana']);
    $user->forceFill(['pending_email' => 'new@shop.test'])->save();

    $html = (new AccountEmailChangeRequested($user))->render();

    expect($html)->toContain(__('mail.account.email_change.title'))
        ->toContain('new@shop.test')
        ->toContain('ajustes/correo/confirmar/'.$user->id.'/'.sha1('new@shop.test'));
});
