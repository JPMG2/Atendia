<?php

declare(strict_types=1);

use App\Enums\MentionStatus;
use App\Models\Business;
use App\Models\BusinessMention;
use App\Models\Company;
use App\Models\ConversationMessage;
use App\Models\MentionGoal;
use App\Models\SocialFollowerCount;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Menciones — the two screens a person looks at
|--------------------------------------------------------------------------
| The owner's card with only the accounts the platform has, and the admin's
| desk where every row is resolved in place. The overflow check measures the
| page; a column cut inside its card is caught by looking at the shot.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

test('the admin desk holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $company = Company::factory()->create();

    foreach (['Instagram' => 'https://instagram.com/aliadito', 'TikTok' => 'https://tiktok.com/@aliadito'] as $name => $url) {
        $link = SocialLink::factory()->create([
            'linkable_id' => $company->id,
            'linkable_type' => Company::class,
            'social_network_id' => SocialNetwork::factory()->create(['name' => $name]),
            'url' => $url,
        ]);
        SocialFollowerCount::factory()->create(['social_link_id' => $link->id, 'followers' => 1200, 'counted_on' => today()->subDays(14)]);
        SocialFollowerCount::factory()->create(['social_link_id' => $link->id, 'followers' => 1342, 'counted_on' => today()->subDays(9)]);
    }

    foreach (['Ferretería El Tornillo', 'Pastelería Dulce Hogar'] as $name) {
        BusinessMention::factory()->create([
            'business_id' => Business::factory()->create(['name' => $name]),
            'platform_accounts' => [
                ['network' => 'Instagram', 'url' => 'https://instagram.com/aliadito'],
                ['network' => 'TikTok', 'url' => 'https://tiktok.com/@aliadito'],
            ],
        ]);
    }

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    $page = visit(route('admin.mentions'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Ferretería El Tornillo')
        ->assertSee(__('mentions.actions.follows'))
        ->assertSee(__('mentions.actions.open_ours', ['network' => 'TikTok']))
        ->screenshot(filename: 'mentions-desk-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
]);

test('the publishing stage shows the text to copy and a place for the link', function (): void {
    BusinessMention::factory()->create([
        'status' => MentionStatus::Ready,
        'caption' => 'Ferretería El Tornillo ya atendió 100 conversaciones en un mes con su asistente.',
    ]);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    visit(route('admin.mentions', ['ver' => 'ready']))->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Ferretería El Tornillo ya atendió')
        ->click(__('mentions.actions.mark_published'))
        ->assertSee(__('mentions.actions.save_published'))
        ->screenshot(filename: 'mentions-publish-step');
});

test('the owner card lists only the registered accounts, at every width', function (string $label, int $width, int $height, bool $dark): void {
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram']);
    $tiktok = SocialNetwork::factory()->create(['name' => 'TikTok']);

    foreach ([[$instagram, 'https://instagram.com/aliadito'], [$tiktok, 'https://tiktok.com/@aliadito']] as $order => [$network, $url]) {
        SocialLink::factory()->create(['linkable_id' => $company->id, 'linkable_type' => Company::class, 'social_network_id' => $network->id, 'url' => $url, 'sort_order' => $order + 1]);
    }

    $business = Business::factory()->create(['name' => 'Ferretería El Tornillo']);
    SocialLink::factory()->for($business, 'linkable')->create(['social_network_id' => $instagram->id, 'url' => 'https://instagram.com/el_tornillo']);

    MentionGoal::factory()->create(['threshold' => 3]);
    ConversationMessage::factory()->count(3)->create(['business_id' => $business->id]);

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $owner->business()->associate($business)->save();
    $this->actingAs($owner->refresh());

    $page = visit(route('dashboard'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee(__('client.mention.accept'))
        ->assertSee(__('client.mention.open_profile', ['network' => 'TikTok']))
        ->assertDontSee(__('client.mention.open_profile', ['network' => 'Facebook']))
        ->screenshot(filename: 'mentions-card-'.$label.'-'.($dark ? 'dark' : 'light'));

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);
