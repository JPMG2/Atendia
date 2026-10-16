<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The referral pages, as a person sees them
|--------------------------------------------------------------------------
| The invite never names who sent it, and neither page puts a percentage on
| a reward that nothing pays yet.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

test('the invite page invites without naming the business that sent it', function (): void {
    $referrer = Business::factory()->create(['name' => 'Ferretería El Tornillo']);

    $page = visit('/r/'.$referrer->referral_code)->resize(1280, 800);

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('referrals.invite.title'))
        ->assertDontSee('Ferretería El Tornillo')
        ->assertSee(__('referrals.invite.cta'))
        ->screenshot(filename: 'referral-invite-light');

    $page->script('document.documentElement.classList.add("dark")');
    $page->screenshot(filename: 'referral-invite-dark');
});

test('the earn screen explains the reward without promising a figure', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();
    $this->actingAs($user);

    visit('/gana')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee(__('referrals.how_reward'))
        ->assertDontSee('25%')
        ->screenshot(filename: 'referral-earn-light');
});
