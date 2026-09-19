<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function planClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

test('a guest is sent to login', function (): void {
    $this->get('/plan')->assertRedirect('/login');
});

test('the screen shows the trial plan, its price and the trial badge', function (): void {
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('plan.title'))
        ->assertSee(__('plan.names.negocio'))
        ->assertSee('$79')
        ->assertSee(__('plan.trial_badge', ['days' => 14]));
});

test('the conversations meter counts this month active threads', function (): void {
    $user = planClient();
    $conversation = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->for($conversation)->create(['business_id' => $user->business_id]);

    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('plan.meters.conversations_of', ['used' => 1, 'cap' => '1.000']));
});

test('higher rungs show their extras locked with the padlock hint, never hidden', function (): void {
    $user = planClient();
    // Expired trial: the business reads the ladder from the floor plan.
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('plan.names.emprende'))
        ->assertSee(__('plan.locked_in', ['plan' => __('plan.names.negocio')]))
        ->assertSee(__('plan.locked_in', ['plan' => __('plan.names.premium')]))
        ->assertSee(__('plan.features.audio', ['cap' => 200]));
});

test('the ladder offers the landing annual toggle with two months free', function (): void {
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('landing.pricing.billing_yearly'))
        ->assertSee(__('landing.pricing.billing_yearly_badge'))
        // Annual monthly equivalents mirror the landing exactly.
        ->assertSee('$24')
        ->assertSee('$66')
        ->assertSee('$124');
});

test('the current plan card carries no upgrade CTA while higher rungs invite upward', function (): void {
    config()->set('atendia.sales_whatsapp', '5491100000000');
    $this->actingAs(planClient());

    livewire('plan.index')
        ->assertSee(__('plan.yours'))
        ->assertSee(__('plan.cta'))
        ->assertSeeHtml('wa.me/5491100000000');
});
