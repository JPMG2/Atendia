<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Events\BusinessCreated;
use App\Mail\ReferralLink;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

test('every business is born with a shareable code free of lookalike characters', function (): void {
    $business = Business::factory()->create();

    expect($business->referral_code)->toHaveLength(8)
        ->toMatch('/^[A-HJ-KM-NP-Z2-9]+$/')
        ->and(Business::factory()->create()->referral_code)->not->toBe($business->referral_code);
});

test('a real code lands on the personal invite page and drops the code', function (): void {
    $referrer = Business::factory()->create(['name' => 'Laboratorio Vida']);

    $this->get('/r/'.$referrer->referral_code)
        ->assertOk()
        ->assertSee(__('referrals.invite.title', ['inviter' => 'Laboratorio Vida']))
        ->assertSee(__('referrals.invite.cta'))
        ->assertSee(route('register'))
        ->assertSessionHas('atendia_ref', $referrer->referral_code)
        ->assertCookie('atendia_ref', $referrer->referral_code, encrypted: false);
});

test('a dead code falls through to the plain register with nothing attributed', function (): void {
    $this->get('/r/GHOSTCOD')
        ->assertRedirect(route('register'))
        ->assertSessionMissing('atendia_ref');
});

test('a business born with the code in session is attributed and gets the longer trial', function (): void {
    $referrer = Business::factory()->create();
    session()->put('atendia_ref', $referrer->referral_code);

    $invited = Business::factory()->create();

    expect($invited->referred_by_business_id)->toBe($referrer->id)
        ->and($referrer->referredCount())->toBe(1)
        ->and((int) ceil(now()->diffInDays($invited->subscription->trial_ends_at)))
        ->toBe((int) config('atendia.referral.invited_trial_days'));
});

test('an unknown code attributes nobody and keeps the standard trial', function (): void {
    session()->put('atendia_ref', 'GHOSTCOD');

    $business = Business::factory()->create();

    expect($business->referred_by_business_id)->toBeNull()
        ->and((int) ceil(now()->diffInDays($business->subscription->trial_ends_at)))
        ->toBe((int) Plan::trial()->trialDays);
});

test('the screen shows the link, the tally and the double-sided deal', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    session()->forget('atendia_ref');

    session()->put('atendia_ref', $user->business->referral_code);
    Business::factory()->create();
    session()->forget('atendia_ref');

    $this->actingAs($user);

    livewire('referrals.index')
        ->assertSee(__('referrals.title'))
        ->assertSee($user->business->referralLink())
        ->assertSee('1')
        ->assertSee(__('referrals.how_reward', ['percent' => 25]))
        ->assertSee(__('referrals.how_invited', ['days' => 21]));
});

test('a guest is sent to login', function (): void {
    $this->get('/gana')->assertRedirect('/login');
});

test('the referral link mail rides the creation event, once, to the account address', function (): void {
    Mail::fake();
    $business = Business::factory()->create(['billing_email' => 'duena@negocio.test']);

    event(new BusinessCreated($business));

    Mail::assertQueued(ReferralLink::class, fn (ReferralLink $mail): bool => $mail->hasTo('duena@negocio.test'));
});

test('the mail carries the shareable link ready to forward', function (): void {
    $business = Business::factory()->create();

    $mail = new ReferralLink($business);

    expect($mail->envelope()->subject)->toBe(__('mail.referral_link.subject'))
        ->and($mail->render())
        ->toContain($business->referralLink())
        ->toContain(route('referrals'));
});

test('the mail rides a real PNG QR, the only raster Gmail will show', function (): void {
    $mail = new ReferralLink(Business::factory()->create());

    expect((string) $mail->content()->with['qrPng'])->toStartWith("\x89PNG");
});

test('open founder seats greet a business that has not referred yet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $this->actingAs($user);

    livewire('referrals.index')
        ->assertSee(trans_choice('referrals.founder_seats', 10, ['count' => 10]));
});

test('the screen resends the link by mail on demand', function (): void {
    Mail::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create(['email' => 'negocio@correo.test']))->save();

    $this->actingAs($user);

    livewire('referrals.index')
        ->call('emailLink')
        ->assertDispatched('notify');

    Mail::assertQueued(ReferralLink::class, fn (ReferralLink $mail): bool => $mail->hasTo('negocio@correo.test'));
});

test('the screen offers the printable QR of the link', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $this->actingAs($user);

    livewire('referrals.index')
        ->assertSee(__('referrals.qr_title'))
        ->assertSee(__('referrals.download_qr'))
        ->assertSeeHtml('<svg');
});

test('the founder badge greets only a business whose link already brought someone', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $this->actingAs($user);

    // Before referring: the open-seats invitation, not the badge itself.
    livewire('referrals.index')->assertSee(trans_choice('referrals.founder_seats', 10, ['count' => 10]));

    session()->put('atendia_ref', $user->business->referral_code);
    Business::factory()->create();
    session()->forget('atendia_ref');

    livewire('referrals.index')
        ->assertSee(__('referrals.founder'))
        ->assertDontSee(trans_choice('referrals.founder_seats', 9, ['count' => 9]));
});
