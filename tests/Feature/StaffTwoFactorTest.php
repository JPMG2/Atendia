<?php

declare(strict_types=1);

use App\Actions\Account\ConfirmWhatsAppTwoFactor;
use App\Actions\Account\SendWhatsAppSetupCode;
use App\Actions\Admin\RemindStaffTwoFactor;
use App\Classes\Main\StaffTwoFactor;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Mail\AccountTwoFactorReset;
use App\Mail\StaffTwoFactorDeadline;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The second step of the platform's own staff
|--------------------------------------------------------------------------
| Admin and support owe it inside a plazo; the owner can see who has it and
| take somebody's away with her password; and the server has a way out for the
| day the WhatsApp channel is down and nobody can sign in to switch it off.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-01-01']);
});

/** A person of the team, with a number to receive codes on. */
function staffAccount(string $role = 'support', array $attributes = []): User
{
    $user = User::factory()->create(['whatsapp' => '5492995550001', 'email_verified_at' => now(), ...$attributes]);
    $user->syncRoles([$role]);

    return $user->refresh();
}

/** The second step already on, as the settings card leaves it. */
function withSecondStep(User $user): User
{
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();

    return $user->refresh();
}

test('the plazo runs from the later of the rule and the account, and is spent when it ends', function (): void {
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-10-09']);
    $this->travelTo('2026-10-12 10:00:00');

    $old = staffAccount('support', ['created_at' => '2026-01-05']);
    $new = staffAccount('support', ['created_at' => '2026-10-11 09:00:00']);

    $oldRule = new StaffTwoFactor($old);
    $newRule = new StaffTwoFactor($new);

    // The old account counts from the day the rule was born, not from its own creation.
    expect($oldRule->deadline->toDateString())->toBe('2026-10-16')
        ->and($oldRule->overdue)->toBeFalse()
        ->and($oldRule->daysLeft)->toBe(4)
        // A new account has its own seven days.
        ->and($newRule->deadline->toDateString())->toBe('2026-10-18');

    $this->travelTo('2026-10-17 10:00:00');

    expect((new StaffTwoFactor($old))->overdue)->toBeTrue()
        ->and((new StaffTwoFactor($old))->daysLeft)->toBe(0);
});

test('nothing is asked of a client, of someone who already has it, or when the platform stops asking', function (): void {
    $client = User::factory()->create();
    $client->syncRoles(['client']);

    expect((new StaffTwoFactor($client))->applies)->toBeFalse()
        ->and((new StaffTwoFactor(withSecondStep(staffAccount())))->applies)->toBeFalse();

    config()->set('atendia.security.staff_two_factor.grace_days', 0);

    expect((new StaffTwoFactor(staffAccount()))->applies)->toBeFalse()
        ->and((new StaffTwoFactor(staffAccount()))->deadline)->toBeNull();
});

test('inside the plazo the panel opens, and a banner says how long is left', function (): void {
    $support = staffAccount();

    $this->actingAs($support)->get(route('admin.support'))->assertOk()->assertSee('para activar el doble factor', false);
});

test('past the plazo the panel opens on one page only: the one where the second step is turned on', function (): void {
    $support = staffAccount('support', ['created_at' => now()->subDays(30)]);
    config()->set('atendia.security.staff_two_factor.since', '2026-01-01');

    $this->actingAs($support)->get(route('admin.support'))->assertRedirect(route('admin.security'));
    $this->actingAs($support)->get(route('admin.dashboard'))->assertRedirect(route('admin.security'));
    $this->actingAs($support)->get(route('admin.security'))->assertOk()->assertSee('El plazo venció', false);
});

test('a tab left open past the plazo does not keep working: the lock rides every Livewire call too', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(RequireStaffTwoFactor::class);
});

test('turning it on opens the panel again, and a person who has it is never sent away', function (): void {
    $support = withSecondStep(staffAccount('support', ['created_at' => now()->subDays(30)]));

    $this->actingAs($support)->get(route('admin.support'))->assertOk();
});

test('the lock is on the route: a client cannot open the security page either', function (): void {
    $client = User::factory()->create();
    $client->syncRoles(['client']);

    $this->actingAs($client)->get(route('admin.security'))->assertForbidden();
});

test('a team member with no business receives the code on the number saved for themselves', function (): void {
    $staff = staffAccount('support', ['whatsapp' => '+54 9 299 555-0001']);

    expect($staff->secondFactorPhone())->toBe('5492995550001');

    // A business owner still reads hers from the business.
    $owner = User::factory()->create();
    $owner->business()->associate(Business::factory()->create(['fallback_whatsapp_number' => '+54 9 11 4444-5555']))->save();

    expect($owner->refresh()->secondFactorPhone())->toBe('5491144445555');
});

test('the security page saves the number as digits and refuses one that cannot receive a code', function (): void {
    $staff = staffAccount('support', ['whatsapp' => null]);

    Livewire::actingAs($staff)->test('admin.security.index')
        ->set('form.whatsapp', '123')
        ->call('savePhone')
        ->assertHasErrors('whatsapp');

    Livewire::actingAs($staff)->test('admin.security.index')
        ->set('form.whatsapp', '+54 9 299 555-0001')
        ->call('savePhone')
        ->assertHasNoErrors();

    expect($staff->refresh()->whatsapp)->toBe('5492995550001');
});

test('turning the second step on ends a reset', function (): void {
    $staff = staffAccount();
    $staff->forceFill(['two_factor_reset_at' => now()])->save();

    Cache::put(SendWhatsAppSetupCode::cacheKey($staff), ['hash' => hash('sha256', '123456'), 'attempts' => 0], now()->addMinutes(10));
    Mail::fake();

    expect(app(ConfirmWhatsAppTwoFactor::class)->handle($staff, '123456'))->toBeTrue();
    expect($staff->refresh()->two_factor_reset_at)->toBeNull()
        ->and((new StaffTwoFactor($staff))->applies)->toBeFalse();
});

test('the owner sees who has the second step and can filter by it', function (): void {
    $admin = staffAccount('admin');
    $with = withSecondStep(staffAccount('support', ['name' => 'Rocío Con']));
    staffAccount('support', ['name' => 'Martín Sin']);

    Livewire::actingAs($admin)->test('admin.users.index')
        ->assertSee('Doble factor: 1 de 3 personas del equipo')
        ->set('factor', 'on')->assertSee('Rocío Con')->assertDontSee('Martín Sin')
        ->set('factor', 'off')->assertSee('Martín Sin')->assertDontSee('Rocío Con');

    expect($with->two_factor_whatsapp_at)->not->toBeNull();
});

test('resetting asks for the owner\'s password, takes the second step away, writes the audit and tells the person', function (): void {
    Mail::fake();
    $admin = staffAccount('admin');
    $admin->forceFill(['password' => 'secret-password-1'])->save();
    $target = withSecondStep(staffAccount('support', ['name' => 'Rocío Paz']));
    $target->forceFill(['two_factor_recovery_codes' => ['a', 'b']])->save();

    // Wrong password: nothing moves.
    Livewire::actingAs($admin)->test('admin.users.index')
        ->call('startReset', $target->id)
        ->set('resetForm.current_password', 'not-her-password')
        ->call('confirmReset')
        ->assertHasErrors('current_password');

    expect($target->refresh()->two_factor_whatsapp_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'two_factor_reset')->count())->toBe(0);

    // Right password.
    Livewire::actingAs($admin)->test('admin.users.index')
        ->call('startReset', $target->id)
        ->set('resetForm.current_password', 'secret-password-1')
        ->call('confirmReset')
        ->assertHasNoErrors();

    $target->refresh();
    $entry = Activity::query()->where('description', 'two_factor_reset')->sole();

    expect($target->two_factor_whatsapp_at)->toBeNull()
        ->and($target->two_factor_recovery_codes)->toBeNull()
        ->and($target->two_factor_reset_at)->not->toBeNull()
        ->and($entry->log_name)->toBe('access')
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($target->id)
        // And the cut is immediate: no grace for somebody the owner reset.
        ->and((new StaffTwoFactor($target))->overdue)->toBeTrue();

    Mail::assertQueued(AccountTwoFactorReset::class, fn (AccountTwoFactorReset $mail): bool => $mail->hasTo($target->email));
});

test('nobody but the owner can reset: the button is not drawn and the action answers 403', function (): void {
    $manager = staffAccount('support');
    $manager->givePermissionTo(['users.view']);
    $target = withSecondStep(staffAccount('support'));

    Livewire::actingAs($manager)->test('admin.users.index')
        ->assertDontSee('two-factor-reset-'.$target->id)
        ->call('startReset', $target->id)
        ->assertForbidden();

    expect($target->refresh()->two_factor_whatsapp_at)->not->toBeNull();
});

test('nobody resets their own second step from the users screen', function (): void {
    $admin = withSecondStep(staffAccount('admin'));
    $admin->forceFill(['password' => 'secret-password-1'])->save();

    Livewire::actingAs($admin)->test('admin.users.index')
        ->call('startReset', $admin->id)
        ->set('resetForm.current_password', 'secret-password-1')
        ->call('confirmReset')
        ->assertHasErrors('current_password');

    expect($admin->refresh()->two_factor_whatsapp_at)->not->toBeNull();
});

test('the server has a way out: the plazo can be set to zero, and one person reset, with no password', function (): void {
    $this->seed(PlatformSettingSeeder::class);
    $person = withSecondStep(staffAccount('support'));

    $this->artisan('atendia:staff-two-factor', ['days' => 0])->assertSuccessful();

    expect(PlatformSetting::query()->where('key', 'security.staff_two_factor.grace_days')->value('value'))->toBe('0');

    $this->artisan('atendia:two-factor-reset', ['email' => $person->email])->assertSuccessful();

    $entry = Activity::query()->where('description', 'two_factor_reset')->sole();

    expect($person->refresh()->two_factor_whatsapp_at)->toBeNull()
        ->and($entry->causer_id)->toBeNull();

    $this->artisan('atendia:two-factor-reset', ['email' => 'nadie@atendia.test'])->assertFailed();
    $this->artisan('atendia:staff-two-factor', ['days' => 99])->assertFailed();
});

test('the day before the plazo ends, whoever still lacks the second step is mailed once', function (): void {
    Mail::fake();
    Cache::flush();
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-10-09']);
    $this->travelTo('2026-10-15 09:05:00');

    // Plazo ends on the 16th: tomorrow.
    $due = staffAccount('support', ['email' => 'due@atendia.test', 'created_at' => '2026-01-05']);
    $covered = withSecondStep(staffAccount('support', ['email' => 'covered@atendia.test']));
    $client = staffAccount('client', ['email' => 'client@negocio.test']);
    $unverified = staffAccount('support', ['email' => 'nuncaentro@atendia.test', 'email_verified_at' => null]);

    $this->artisan('atendia:staff-two-factor-warning')->assertSuccessful();

    Mail::assertQueued(StaffTwoFactorDeadline::class, 1);
    Mail::assertQueued(StaffTwoFactorDeadline::class, fn (StaffTwoFactorDeadline $mail): bool => $mail->hasTo('due@atendia.test')
        && $mail->deadline->toDateString() === '2026-10-16');

    // The same day, a second run says nothing new.
    $this->artisan('atendia:staff-two-factor-warning')->assertSuccessful();
    Mail::assertQueued(StaffTwoFactorDeadline::class, 1);

    expect($covered->email)->not->toBe($due->email)
        ->and($client->email)->not->toBe($due->email)
        ->and($unverified->email_verified_at)->toBeNull();
});

test('two days out or already past, nobody is mailed', function (): void {
    Mail::fake();
    Cache::flush();
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-10-09']);
    staffAccount('support', ['created_at' => '2026-01-05']);

    $this->travelTo('2026-10-14 09:05:00');
    $this->artisan('atendia:staff-two-factor-warning')->assertSuccessful();

    $this->travelTo('2026-10-17 09:05:00');
    $this->artisan('atendia:staff-two-factor-warning')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the warning mail says the date, where to act, and renders in both parts', function (): void {
    $this->travelTo('2026-10-15 09:05:00');
    $person = staffAccount('support', ['name' => 'Rocío Paz']);
    $mail = new StaffTwoFactorDeadline($person, CarbonImmutable::parse('2026-10-16 10:00:00'));

    $mail->assertSeeInHtml('Rocío Paz')
        ->assertSeeInHtml('16/10/2026')
        ->assertSeeInHtml(route('admin.security'))
        ->assertSeeInText('16/10/2026');

    expect($mail->envelope()->subject)->toBe('Mañana vence tu plazo para activar la verificación en dos pasos');
});

test('with days to spare the mail names the date and never says tomorrow', function (): void {
    $this->travelTo('2026-10-12 09:05:00');
    $mail = new StaffTwoFactorDeadline(staffAccount(), CarbonImmutable::parse('2026-10-16 10:00:00'));

    $mail->assertSeeInHtml('Tu plazo vence el 16/10/2026')->assertDontSeeInHtml('Mañana');

    expect($mail->envelope()->subject)->toBe('Tu plazo para activar la verificación en dos pasos vence el 16/10/2026');
});

test('the owner reminds a person whose plazo is running, once a day, and nobody else', function (): void {
    Mail::fake();
    Cache::flush();
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-10-09']);
    $this->travelTo('2026-10-12 10:00:00');

    $owner = staffAccount('admin');
    $running = staffAccount('support', ['created_at' => '2026-01-05']);
    $covered = withSecondStep(staffAccount('support'));
    $remind = new RemindStaffTwoFactor;

    expect($remind->handle($running, $owner))->toBe(RemindStaffTwoFactor::SENT)
        ->and($remind->handle($running, $owner))->toBe(RemindStaffTwoFactor::ALREADY)
        ->and($remind->handle($covered, $owner))->toBe(RemindStaffTwoFactor::NOT_DUE);

    Mail::assertQueued(StaffTwoFactorDeadline::class, 1);

    // The morning run does not repeat what the button already said today.
    $this->travelTo('2026-10-15 09:05:00');
    $remind->handle($running, $owner);
    $this->artisan('atendia:staff-two-factor-warning')->assertSuccessful();
    Mail::assertQueued(StaffTwoFactorDeadline::class, 2);

    // Past the plazo the panel already says it: no mail.
    $this->travelTo('2026-10-18 09:05:00');
    expect($remind->handle($running, $owner))->toBe(RemindStaffTwoFactor::NOT_DUE);
});

test('reminding is a key of its own: support cannot press it', function (): void {
    config()->set('atendia.security.staff_two_factor', ['grace_days' => 7, 'since' => '2026-10-09']);
    $this->travelTo('2026-10-12 10:00:00');

    $target = staffAccount('support');

    expect(fn () => (new RemindStaffTwoFactor)->handle($target, staffAccount('support')))
        ->toThrow(AuthorizationException::class);
});

test('a role can be given the plazo-free permission only by the owner: the keys are seeded', function (): void {
    expect(Role::findByName('admin')->hasPermissionTo('reset-two-factor'))->toBeTrue()
        ->and(Role::findByName('support')->hasPermissionTo('reset-two-factor'))->toBeFalse();
});
