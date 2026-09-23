<?php

declare(strict_types=1);

use App\Mail\AccountClosed;
use App\Models\Business;
use App\Models\User;
use App\Services\SignedLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Closing the account — a soft delete, never an erase
|--------------------------------------------------------------------------
| The danger zone asks for a typed word and the password. Closing soft-
| deletes the user AND the business (the data is worth keeping), ends the
| session and mails the way back; inside the restore window the link or a
| plain sign-in brings everything back.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

function ownerWithBusiness(): User
{
    $business = Business::factory()->create();

    return User::factory()->create(['business_id' => $business->id, 'email' => 'owner@shop.test'])->refresh();
}

test('closing demands the typed word and the password', function (): void {
    $user = ownerWithBusiness();
    $this->actingAs($user);

    Livewire::test('settings.section-close')
        ->set('form.confirmation', 'borrar')
        ->set('form.current_password', 'password')
        ->call('close')
        ->assertHasErrors('confirmation');

    Livewire::test('settings.section-close')
        ->set('form.confirmation', __('settings.close.keyword'))
        ->set('form.current_password', 'wrong')
        ->call('close')
        ->assertHasErrors('current_password');

    expect($user->refresh()->trashed())->toBeFalse();
});

test('closing soft-deletes the account and its business, signs out and mails the way back', function (): void {
    $user = ownerWithBusiness();
    $businessId = $user->business_id;
    $this->actingAs($user);

    Livewire::test('settings.section-close')
        ->set('form.confirmation', __('settings.close.keyword'))
        ->set('form.current_password', 'password')
        ->call('close')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertSoftDeleted('businesses', ['id' => $businessId]);

    Mail::assertQueued(AccountClosed::class, fn ($mail): bool => $mail->hasTo('owner@shop.test'));
});

test('the restore link brings the account and its business back', function (): void {
    $user = ownerWithBusiness();
    $business = $user->business;
    $business->delete();
    $user->delete();

    $url = SignedLink::temporary('account.restore', now()->addDays(30), ['user' => $user->id]);

    $this->get($url)->assertSuccessful()->assertSee(__('settings.links.restored_title'));

    expect($user->refresh()->trashed())->toBeFalse()
        ->and($business->refresh()->trashed())->toBeFalse();
});

test('past the restore window the owner cannot come back alone', function (): void {
    $user = ownerWithBusiness();
    $user->delete();
    $user->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    $url = SignedLink::temporary('account.restore', now()->addDay(), ['user' => $user->id]);

    $this->get($url)->assertSee(__('settings.links.restore_expired_title'));

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('signing in inside the window restores the account', function (): void {
    $user = ownerWithBusiness();
    $user->business->delete();
    $user->delete();

    $this->post('/login', ['email' => 'owner@shop.test', 'password' => 'password']);

    $this->assertAuthenticatedAs($user->refresh());
    expect($user->business()->exists())->toBeTrue();
});

test('a wrong password never restores a closed account', function (): void {
    $user = ownerWithBusiness();
    $user->delete();

    $this->post('/login', ['email' => 'owner@shop.test', 'password' => 'wrong']);

    $this->assertGuest();
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('the goodbye mail carries the restore link', function (): void {
    $user = ownerWithBusiness();

    expect((new AccountClosed($user))->render())
        ->toContain(__('mail.account.closed.title'))
        ->toContain('cuenta/restaurar/'.$user->id);
});
