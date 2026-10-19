<?php

declare(strict_types=1);

use App\Actions\Business\RaiseMentionOffer;
use App\Enums\MentionStatus;
use App\Enums\PanelNotificationType;
use App\Enums\PlatformEventKind;
use App\Models\Business;
use App\Models\BusinessMention;
use App\Models\Company;
use App\Models\ConversationMessage;
use App\Models\MentionGoal;
use App\Models\PanelNotification;
use App\Models\PlatformEvent;
use App\Models\SocialFollowerCount;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use App\Models\User;
use Database\Seeders\MentionGoalSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * An owner whose business reached a goal of three conversations, on a platform
 * with Instagram and TikTok registered. The business only has Instagram.
 */
function mentionOwner(bool $withOwnInstagram = true, int $conversations = 3): User
{
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram']);
    $tiktok = SocialNetwork::factory()->create(['name' => 'TikTok']);

    SocialLink::factory()->create(['linkable_id' => $company->id, 'linkable_type' => Company::class, 'social_network_id' => $instagram->id, 'url' => 'https://instagram.com/aliadito', 'sort_order' => 1]);
    SocialLink::factory()->create(['linkable_id' => $company->id, 'linkable_type' => Company::class, 'social_network_id' => $tiktok->id, 'url' => 'https://tiktok.com/@aliadito', 'sort_order' => 2]);

    $business = Business::factory()->create(['name' => 'Ferretería El Tornillo']);

    if ($withOwnInstagram) {
        SocialLink::factory()->for($business, 'linkable')->create(['social_network_id' => $instagram->id, 'url' => 'https://instagram.com/el_tornillo']);
    }

    MentionGoal::factory()->create(['threshold' => 3, 'caption' => '{negocio} ya atendió {logro}.']);
    ConversationMessage::factory()->count($conversations)->create(['business_id' => $business->id]);

    $user = User::factory()->create();
    $user->business()->associate($business)->save();

    return $user;
}

function mentionAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('the card stays quiet while no goal is reached', function (): void {
    $this->actingAs(mentionOwner(conversations: 2));

    livewire('client.mention-offer')->assertDontSee(__('client.mention.offer.title', ['achievement' => '']));
});

test('a reached goal offers the mention and lists only the accounts the platform registered', function (): void {
    $this->actingAs(mentionOwner());

    livewire('client.mention-offer')
        ->assertSee(MentionGoal::achievement(3))
        ->assertSee(__('client.mention.open_profile', ['network' => 'Instagram']))
        ->assertSee(__('client.mention.open_profile', ['network' => 'TikTok']))
        ->assertDontSee(__('client.mention.open_profile', ['network' => 'Facebook']))
        ->assertSee(__('client.mention.accept'));
});

test('accepting stores the consent with the accounts and the post, and warns the admin', function (): void {
    $owner = mentionOwner();
    $this->actingAs($owner);

    livewire('client.mention-offer')->call('accept');

    $mention = BusinessMention::query()->sole();

    expect($mention->business_id)->toBe($owner->business_id)
        ->and($mention->status)->toBe(MentionStatus::Pending)
        ->and($mention->goal_threshold)->toBe(3)
        ->and($mention->business_account_url)->toBe('https://instagram.com/el_tornillo')
        ->and(collect($mention->platform_accounts)->pluck('network')->all())->toBe(['Instagram', 'TikTok'])
        ->and($mention->caption)->toBe('Ferretería El Tornillo ya atendió '.MentionGoal::achievement(3).'.')
        ->and($mention->answered_at)->not->toBeNull()
        ->and(PlatformEvent::query()->where('kind', PlatformEventKind::MentionAccepted->value)->exists())->toBeTrue();
});

test('without an account where the platform is, the card asks for one and cannot accept', function (): void {
    $this->actingAs(mentionOwner(withOwnInstagram: false));

    livewire('client.mention-offer')
        ->assertSee(__('client.mention.add_network'))
        ->assertDontSee(__('client.mention.accept'))
        ->call('accept');

    expect(BusinessMention::query()->count())->toBe(0);
});

test('saying not now is remembered and the same goal never comes back', function (): void {
    $this->actingAs(mentionOwner());

    livewire('client.mention-offer')->call('dismiss')->assertDontSee(__('client.mention.accept'));

    expect(BusinessMention::query()->sole()->status)->toBe(MentionStatus::Dismissed);

    $this->travel(12)->months();

    livewire('client.mention-offer')->assertDontSee(__('client.mention.accept'));
});

test('a bigger goal reopens the offer only after the pause', function (): void {
    $owner = mentionOwner();
    $this->actingAs($owner);
    livewire('client.mention-offer')->call('dismiss');

    MentionGoal::factory()->create(['threshold' => 4]);
    ConversationMessage::factory()->create(['business_id' => $owner->business_id]);

    livewire('client.mention-offer')->assertDontSee(__('client.mention.accept'));

    $this->travel(4)->months();
    ConversationMessage::factory()->count(4)->create(['business_id' => $owner->business_id]);

    livewire('client.mention-offer')->assertSee(MentionGoal::achievement(4));
});

test('after the allowed number of refusals the offer never returns', function (): void {
    $owner = mentionOwner();
    config(['atendia.mentions.max_refusals' => 2]);
    BusinessMention::factory()->count(2)->sequence(['goal_threshold' => 1], ['goal_threshold' => 2])->create([
        'business_id' => $owner->business_id,
        'status' => MentionStatus::Dismissed,
        'answered_at' => now()->subYear(),
    ]);
    MentionGoal::factory()->create(['threshold' => 50]);
    ConversationMessage::factory()->count(50)->create(['business_id' => $owner->business_id]);

    $this->actingAs($owner);

    livewire('client.mention-offer')->assertDontSee(__('client.mention.accept'));
});

test('an agent without business rights never sees the card', function (): void {
    $owner = mentionOwner();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles('agent');

    $this->actingAs($agent);

    livewire('client.mention-offer')->assertDontSee(__('client.mention.accept'));
});

test('the owner can withdraw before the post is out, and it counts as a no', function (): void {
    $owner = mentionOwner();
    $mention = BusinessMention::factory()->create(['business_id' => $owner->business_id, 'status' => MentionStatus::Ready]);

    $this->actingAs($owner);

    livewire('client.mention-offer')->call('withdraw');

    expect($mention->refresh()->status)->toBe(MentionStatus::Withdrawn);
});

test('a published mention cannot be withdrawn', function (): void {
    $owner = mentionOwner();
    $mention = BusinessMention::factory()->create(['business_id' => $owner->business_id, 'status' => MentionStatus::Published]);

    $this->actingAs($owner);

    livewire('client.mention-offer')->call('withdraw');

    expect($mention->refresh()->status)->toBe(MentionStatus::Published);
});

test('the admin verifies the follow and the mention moves to publishing', function (): void {
    $mention = BusinessMention::factory()->create();
    $admin = mentionAdmin();
    $this->actingAs($admin);

    livewire('admin.mentions.index')
        ->assertSee($mention->business->name)
        ->call('verify', $mention->id);

    expect($mention->refresh()->status)->toBe(MentionStatus::Ready)
        ->and($mention->verified_by)->toBe($admin->id)
        ->and($mention->verified_at)->not->toBeNull();
});

test('telling the business it does not follow puts the reason on its bell and waits for them', function (): void {
    $mention = BusinessMention::factory()->create();
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')->call('reject', $mention->id);

    expect($mention->refresh()->status)->toBe(MentionStatus::NotFollowing)
        ->and(PanelNotification::query()->where('business_id', $mention->business_id)->where('type', PanelNotificationType::MentionNeedsFollow->value)->exists())->toBeTrue();

    $owner = User::factory()->create();
    $owner->business()->associate($mention->business)->save();
    $this->actingAs($owner);

    livewire('client.mention-offer')->call('confirmFollow');

    expect($mention->refresh()->status)->toBe(MentionStatus::Pending);
});

test('only a pending mention can be verified: a stale click does nothing', function (): void {
    $mention = BusinessMention::factory()->create(['status' => MentionStatus::Published]);
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')->call('verify', $mention->id);

    expect($mention->refresh()->status)->toBe(MentionStatus::Published);
});

test('publishing needs the link of the post and tells the business on its bell', function (): void {
    $mention = BusinessMention::factory()->create(['status' => MentionStatus::Ready]);
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index', ['stage' => 'ready'])
        ->call('startPublish', $mention->id)
        ->set('form.post_url', 'no es un enlace')
        ->call('publish')
        ->assertHasErrors('post_url')
        ->set('form.post_url', 'http://instagram.com/p/abc')
        ->call('publish')
        ->assertHasErrors('post_url');

    expect($mention->refresh()->status)->toBe(MentionStatus::Ready);

    livewire('admin.mentions.index', ['stage' => 'ready'])
        ->call('startPublish', $mention->id)
        ->set('form.post_url', 'https://instagram.com/p/abc')
        ->call('publish')
        ->assertHasNoErrors();

    expect($mention->refresh()->status)->toBe(MentionStatus::Published)
        ->and($mention->post_url)->toBe('https://instagram.com/p/abc')
        ->and(PanelNotification::query()->where('business_id', $mention->business_id)->where('type', PanelNotificationType::MentionPublished->value)->exists())->toBeTrue();
});

test('the desk shows each stage with the text ready to copy and the follow accounts', function (): void {
    BusinessMention::factory()->create(['status' => MentionStatus::Ready, 'caption' => 'Texto listo para el post']);
    $pending = BusinessMention::factory()->create();
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index', ['stage' => 'ready'])
        ->assertSee('Texto listo para el post')
        ->assertSee(__('mentions.actions.copy'));

    livewire('admin.mentions.index')
        ->assertSee($pending->business->name)
        ->assertSee(__('mentions.actions.open_ours', ['network' => 'Instagram']))
        ->assertSee(__('mentions.actions.follows'));
});

test('an empty desk says so instead of drawing a blank table', function (): void {
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')->assertSee(__('mentions.empty.pending.title'));
});

test('the mentions desk is locked: a client gets a 403 and an admin gets in', function (): void {
    $this->actingAs(mentionOwner())->get(route('admin.mentions'))->assertForbidden();

    $this->actingAs(mentionAdmin())->get(route('admin.mentions'))->assertOk();
});

test('support staff without the key cannot open the desk', function (): void {
    $support = User::factory()->create();
    $support->syncRoles('support');

    $this->actingAs($support)->get(route('admin.mentions'))->assertForbidden();
});

test('reaching a goal puts one notice on the bell, however many times it is checked', function (): void {
    $owner = mentionOwner();

    app(RaiseMentionOffer::class)->handle($owner->business);
    app(RaiseMentionOffer::class)->handle($owner->business);

    expect(PanelNotification::query()->where('type', PanelNotificationType::MentionOffered->value)->count())->toBe(1);
});

test('no notice is raised while the goal is not reached', function (): void {
    $owner = mentionOwner(conversations: 1);

    app(RaiseMentionOffer::class)->handle($owner->business);

    expect(PanelNotification::query()->count())->toBe(0);
});

test('counting followers stores today\'s number for one of our accounts and shows it with its date', function (): void {
    mentionOwner();
    $instagram = Company::query()->sole()->socialLinks()->orderBy('sort_order')->first();
    $admin = mentionAdmin();
    $this->actingAs($admin);

    livewire('admin.mentions.index')
        ->assertSee(__('mentions.followers.never'))
        ->call('startCount', $instagram->id)
        ->set('count.followers', '1250')
        ->call('saveCount')
        ->assertHasNoErrors();

    // An island re-renders on its own request, so the number is read on a fresh mount.
    livewire('admin.mentions.index')
        ->assertSee('1.250')
        ->assertSee(today()->format('d/m/Y'));

    $saved = SocialFollowerCount::query()->sole();

    expect($saved->followers)->toBe(1250)
        ->and($saved->counted_by)->toBe($admin->id);
});

test('counting twice on the same day corrects the number instead of stacking another', function (): void {
    mentionOwner();
    $link = Company::query()->sole()->socialLinks()->first();
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')
        ->call('startCount', $link->id)->set('count.followers', '100')->call('saveCount')
        ->call('startCount', $link->id)->set('count.followers', '120')->call('saveCount');

    expect(SocialFollowerCount::query()->count())->toBe(1)
        ->and(SocialFollowerCount::query()->sole()->followers)->toBe(120);
});

test('a count must be a whole number and never for an account that is not ours', function (): void {
    $owner = mentionOwner();
    $own = SocialLink::query()->where('linkable_type', Business::class)->where('linkable_id', $owner->business_id)->sole();
    $ours = Company::query()->sole()->socialLinks()->first();
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')
        ->call('startCount', $ours->id)
        ->set('count.followers', 'muchos')
        ->call('saveCount')
        ->assertHasErrors('followers')
        ->set('count.followers', '-5')
        ->call('saveCount')
        ->assertHasErrors('followers')
        ->call('startCount', $own->id)
        ->set('count.followers', '40')
        ->call('saveCount');

    expect(SocialFollowerCount::query()->count())->toBe(0);
});

test('the change since the last count comes with the mentions published in between, and an old count is flagged', function (): void {
    $owner = mentionOwner();
    $link = Company::query()->sole()->socialLinks()->orderBy('sort_order')->first();

    SocialFollowerCount::factory()->create(['social_link_id' => $link->id, 'followers' => 100, 'counted_on' => today()->subDays(20)]);
    SocialFollowerCount::factory()->create(['social_link_id' => $link->id, 'followers' => 130, 'counted_on' => today()->subDays(10)]);
    BusinessMention::factory()->create([
        'business_id' => $owner->business_id,
        'social_network_id' => $link->social_network_id,
        'status' => MentionStatus::Published,
        'published_at' => today()->subDays(15),
    ]);

    $board = SocialFollowerCount::board()->first();

    expect($board['change'])->toBe(30)
        ->and($board['mentions'])->toBe(1)
        ->and($board['stale'])->toBeTrue();

    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')
        ->assertSee(__('mentions.followers.since', ['change' => '+30']))
        ->assertSee(__('mentions.followers.stale'));
});

test('an account counted only once has nothing to compare yet and a fresh count is not stale', function (): void {
    mentionOwner();
    $link = Company::query()->sole()->socialLinks()->orderBy('sort_order')->first();
    SocialFollowerCount::factory()->create(['social_link_id' => $link->id, 'followers' => 80, 'counted_on' => today()]);

    $board = SocialFollowerCount::board()->first();

    expect($board['change'])->toBeNull()
        ->and($board['stale'])->toBeFalse();
});

test('the followers card says what is missing when the platform has no accounts', function (): void {
    $this->actingAs(mentionAdmin());

    livewire('admin.mentions.index')->assertSee(__('mentions.followers.empty_title'));
});

test('the goals master hands the default goals to the platform and keeps her edits', function (): void {
    $this->seed(MentionGoalSeeder::class);

    expect(MentionGoal::query()->orderBy('threshold')->pluck('threshold')->all())->toBe([100, 500, 1000]);

    MentionGoal::query()->where('threshold', 100)->update(['caption' => 'Mi texto']);
    $this->seed(MentionGoalSeeder::class);

    expect(MentionGoal::query()->where('threshold', 100)->value('caption'))->toBe('Mi texto');
});

test('the goals editor creates a goal and refuses a repeated number or a caption that is too long', function (): void {
    MentionGoal::factory()->create(['threshold' => 100]);

    livewire('catalog.mention-goal')
        ->set('form.data.threshold', 250)
        ->set('form.data.caption', '{negocio} ya atendió {logro}. ¡Gracias por confiar!')
        ->call('create')
        ->assertHasNoErrors();

    livewire('catalog.mention-goal')
        ->set('form.data.threshold', 100)
        ->set('form.data.caption', '{negocio} ya atendió {logro}.')
        ->call('create')
        ->assertHasErrors('threshold');

    livewire('catalog.mention-goal')
        ->set('form.data.threshold', 300)
        ->set('form.data.caption', str_repeat('a', MentionGoal::CAPTION_MAX + 1))
        ->call('create')
        ->assertHasErrors('caption');

    expect(MentionGoal::query()->pluck('threshold')->all())->toBe([100, 250]);
});
