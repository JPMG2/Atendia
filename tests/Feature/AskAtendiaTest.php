<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\AskAtendia;
use App\Classes\Main\Plan;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Interfaces\Main\AssistantSkillTool;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\AiUsage;
use App\Models\AskFeedback;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use App\Services\AssistantSkills;
use Database\Seeders\AssistantSkillSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(AssistantSkillSeeder::class);
});

function askClient(): User
{
    $user = User::factory()->create(['name' => 'Carla Ruiz']);
    $user->assignRole('client');
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

function spendAskQuestions(User $user, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        AiUsage::query()->create(['business_id' => $user->business_id, 'kind' => AiUsage::ASK_ATENDIA, 'model' => 'm']);
    }
}

test('each plan carries its monthly question quota, the floor plan none', function (): void {
    expect(Plan::named('emprende')->allowsAsk)->toBeFalse()
        ->and(Plan::named('negocio')->askPerMonth)->toBe(100)
        ->and(Plan::named('premium')->askPerMonth)->toBe(500);
});

test('the panel opens with the assistant introducing itself and the quota left', function (): void {
    $user = askClient();
    spendAskQuestions($user, 1);

    $this->actingAs($user);

    livewire('client.ask-atendia')
        ->call('open')
        ->assertSee(__('ask.hello', ['name' => 'Carla']))
        ->assertSeeHtml('<strong class="ask-hello-brand">asistente <span class="ask-hello-ia">IA</span> de Atendia</strong>')
        ->assertSee(__('ask.suggestions.unresolved'))
        ->assertSeeHtml('<b>99</b>')
        ->assertSee(__('ask.quota.of_short', ['cap' => 100]))
        ->assertDontSee(__('ask.locked.cta'));
});

test('a question reaches the agent and its answer joins the thread', function (): void {
    AskAtendia::fake(['Hoy entraron 3 conversaciones.']);
    $this->actingAs(askClient());

    livewire('client.ask-atendia')
        ->call('open')
        ->set('form.question', '¿Qué consultaron hoy?')
        ->call('ask')
        ->assertSet('messages', [
            ['role' => 'owner', 'text' => '¿Qué consultaron hoy?'],
            ['role' => 'ai', 'text' => 'Hoy entraron 3 conversaciones.', 'rating' => null],
        ])
        ->assertSet('form.question', '');

    AskAtendia::assertPrompted('¿Qué consultaron hoy?');
});

test('an empty question never reaches the agent', function (): void {
    AskAtendia::fake();
    $this->actingAs(askClient());

    livewire('client.ask-atendia')
        ->call('open')
        ->set('form.question', '   ')
        ->call('ask')
        ->assertHasErrors(['question']);

    AskAtendia::assertNeverPrompted();
});

test('the floor plan sees the locked panel and cannot ask even by calling the action', function (): void {
    AskAtendia::fake();
    $user = askClient();
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('client.ask-atendia')
        ->call('open')
        ->assertSee(__('ask.locked.plan', ['plan' => __('plan.names.negocio'), 'cap' => 100]))
        ->assertSee(route('my-plan'))
        ->assertDontSee(__('ask.placeholder'))
        ->set('form.question', '¿Qué consultaron hoy?')
        ->call('ask')
        ->assertSet('messages', []);

    AskAtendia::assertNeverPrompted();
});

test('a spent quota closes the input until next month', function (): void {
    AskAtendia::fake();
    $user = askClient();
    spendAskQuestions($user, 100);

    $this->actingAs($user);

    livewire('client.ask-atendia')
        ->call('open')
        ->assertSee(__('ask.used_up', ['cap' => 100, 'date' => now()->startOfMonth()->addMonthNoOverflow()->format('d/m/Y')]))
        ->assertDontSee(__('ask.placeholder'))
        ->set('form.question', '¿Qué consultaron hoy?')
        ->call('ask');

    AskAtendia::assertNeverPrompted();
});

test('only this month questions of this assistant spend the quota', function (): void {
    $user = askClient();
    spendAskQuestions($user, 1);
    AiUsage::query()->latest('id')->first()->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();
    AiUsage::query()->create(['business_id' => $user->business_id, 'kind' => 'AsistenteAtendia', 'model' => 'm']);

    expect($user->business->askQuestionsThisMonth())->toBe(0);
});

test('an answer renders only its own links live and escapes everything else', function (): void {
    $this->actingAs(askClient());
    $inside = route('customers');

    $html = livewire('client.ask-atendia')->instance()
        ->answerHtml("Mirá [tus clientes]({$inside}) o [esto](https://evil.test/x) <script>x</script>");

    expect($html)->toContain('<a href="'.$inside.'" wire:navigate class="ask-link">tus clientes</a>')
        ->not->toContain('evil.test')
        ->not->toContain('<script>');
});

test('the agent knows today as a date, declines the world and has no web tool', function (): void {
    $user = askClient();
    $agent = new AskAtendia($user->business, 'Carla');
    $today = now($user->business->localTimezone())->toDateString();

    expect((string) $agent->instructions())->toContain("hoy = {$today}")
        ->toContain('Solo puedo ayudarte con tu negocio y con el panel de Atendia.')
        ->toContain('No tenés acceso a internet')
        ->and(collect($agent->tools())->every(fn ($tool): bool => $tool instanceof OwnerSkillTool))->toBeTrue()
        ->and(collect($agent->tools())->map(fn ($tool): string => $tool::class)->all())->toHaveCount(5);
});

test('the owner skills never reach the WhatsApp assistant', function (): void {
    $tools = app(AssistantSkills::class)->for(new AsistenteAtendia(askClient()->business));

    expect(collect($tools)->every(fn ($tool): bool => $tool instanceof AssistantSkillTool))->toBeTrue();
});

test('the icon button rides the client topbar but never the admin panel', function (): void {
    $this->actingAs(askClient())->get('/dashboard')
        ->assertSee('data-testid="ask-atendia"', false)
        ->assertDontSee('Ctrl K');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin')->assertDontSee('data-testid="ask-atendia"', false);
});

test('the plan cards light up the assistant on the tiers that include it', function (): void {
    $this->get('/')
        ->assertSee('pricing-ai', false)
        ->assertSee(__('landing.pricing.ask_title'))
        ->assertSeeInOrder(['pricing-ai-number">100', 'pricing-ai-number">500'], false)
        ->assertSee(trans_choice('plan.features.numbers', 4, ['cap' => 4]));

    $user = askClient();
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('plan.features.ask', ['cap' => 100]))
        ->assertSee(__('plan.features.ask', ['cap' => 500]));
});

test('the day card opens with what needs the owner, each with its screen', function (): void {
    $user = askClient();
    $thread = Conversation::factory()->create(['business_id' => $user->business_id, 'status' => ConversationStatus::Team]);
    ConversationMessage::factory()->for($thread)->create(['business_id' => $user->business_id, 'direction' => MessageDirection::In]);
    Customer::factory()->create(['business_id' => $user->business_id, 'birthday' => now($user->business->localTimezone())->subYears(30)->toDateString()]);
    Conversation::factory()->create(['business_id' => Business::factory()->create()->id, 'status' => ConversationStatus::Team]);

    $this->actingAs($user);

    livewire('client.ask-atendia')
        ->call('close')
        ->assertDontSee(__('ask.today.title'))
        ->call('open')
        ->assertSee(__('ask.today.title'))
        ->assertSee(trans_choice('ask.today.waiting', 1, ['count' => 1]))
        ->assertSee(trans_choice('ask.today.birthdays', 1, ['count' => 1]))
        ->assertSee(trans_choice('ask.today.conversations', 1, ['count' => 1]))
        ->assertDontSee(trans_choice('ask.today.to_teach', 1, ['count' => 1]))
        ->assertSee(route('customers'));
});

test('the panel is drawn only while open, never hidden in every page', function (): void {
    $this->actingAs(askClient());

    livewire('client.ask-atendia')
        ->assertDontSeeHtml('class="slide-over"')
        ->call('open')
        ->assertSeeHtml('class="slide-over"')
        ->call('close')
        ->assertDontSeeHtml('class="slide-over"');
});

test('a quiet day says so instead of an empty card', function (): void {
    $this->actingAs(askClient());

    livewire('client.ask-atendia')->call('open')->assertSee(__('ask.today.clear'));
});

test('a thumb is kept once per answer, with its question', function (): void {
    AskAtendia::fake(['Hoy entraron 3 conversaciones.']);
    $user = askClient();
    $this->actingAs($user);

    livewire('client.ask-atendia')
        ->call('open')
        ->set('form.question', '¿Qué consultaron hoy?')
        ->call('ask')
        ->call('rate', 1, 'down')
        ->call('rate', 1, 'up')
        ->call('rate', 0, 'up')
        ->assertSet('messages.1.rating', 'down')
        ->assertSee(__('ask.rate.thanks'));

    expect(AskFeedback::query()->sole())
        ->business_id->toBe($user->business_id)
        ->question->toBe('¿Qué consultaron hoy?')
        ->answer->toBe('Hoy entraron 3 conversaciones.')
        ->rating->toBe(AskFeedback::DOWN);
});

test('a failed answer offers no thumbs', function (): void {
    AskAtendia::fake(fn () => throw new RuntimeException('provider down'));
    $this->actingAs(askClient());

    livewire('client.ask-atendia')
        ->call('open')
        ->set('form.question', '¿Qué consultaron hoy?')
        ->call('ask')
        ->assertSee(__('ask.failed'))
        ->call('rate', 1, 'up');

    expect(AskFeedback::query()->count())->toBe(0);
});

test('every statistics block offers to ask the assistant about itself', function (): void {
    $user = askClient();
    $user->business->subscription->update(['plan' => 'premium']);
    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('ask.chart.button'))
        ->assertSee("\$dispatch('ask-atendia'", false);
});

test('a suggested question travels straight to the agent, leaving the input alone', function (): void {
    AskAtendia::fake(['Nada pendiente.']);
    $this->actingAs(askClient());

    livewire('client.ask-atendia')
        ->call('open')
        ->call('ask', '¿Qué conversaciones quedaron sin resolver?')
        ->assertSet('messages.0.text', '¿Qué conversaciones quedaron sin resolver?')
        ->assertSet('form.question', '');

    AskAtendia::assertPrompted('¿Qué conversaciones quedaron sin resolver?');
});

test('Mi plan gives the assistant quota its own card with what is left and when it renews', function (): void {
    $user = askClient();
    spendAskQuestions($user, 13);
    $this->actingAs($user);

    livewire('plan.index')
        ->assertSee(__('ask.quota.of', ['cap' => 100]))
        ->assertSeeHtml('<b class="ask-quota-number">87</b>')
        ->assertSee(trans_choice('ask.quota.used', 13, ['count' => 13]))
        ->assertSee(__('ask.quota.renews', ['date' => $user->business->quotaRenewsOn()->format('d/m/Y')]));
});

test('the floor plan shows no quota card', function (): void {
    $user = askClient();
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);
    $this->actingAs($user);

    livewire('plan.index')->assertDontSee(__('ask.quota.of', ['cap' => 100]));
});
