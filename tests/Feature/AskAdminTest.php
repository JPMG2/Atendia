<?php

declare(strict_types=1);

use App\Actions\Admin\RaisePlatformEvent;
use App\Ai\Agents\AskAdmin;
use App\Ai\Tools\AdminAlerts;
use App\Ai\Tools\AdminBusinessFile;
use App\Ai\Tools\AdminFindBusiness;
use App\Ai\Tools\AdminPaymentQueue;
use App\Ai\Tools\AdminPlatformPulse;
use App\Classes\Main\AdminAttention;
use App\Classes\Main\AnswerHtml;
use App\Classes\Main\AssistantContract;
use App\Classes\Main\ScreenTexts;
use App\Enums\PlatformEventKind;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AssistantSkillSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The admin's AI assistant
|--------------------------------------------------------------------------
| It reads across every business, so the key, the exact numbers and what it
| must NEVER say (what a business's customers wrote) are what is judged here.
| The model is faked: nothing in this file spends a token.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function askAdminUser(string $role = 'admin'): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Juan Pablo']);
    $user->assignRole($role);

    return tap($user->refresh(), fn (User $user) => test()->actingAs($user));
}

function asAdminTool(string $tool, array $arguments = []): string
{
    $agent = new AskAdmin(askAdminUser());

    return (string) $tool::forAdmin($agent)->handle(new Request($arguments));
}

describe('who gets it', function (): void {
    test('the admin topbar carries the button for the key holder and for nobody else', function (): void {
        $this->actingAs(askAdminUser())->get(route('admin.dashboard'))->assertOk()->assertSee('data-testid="ask-admin"', false);

        $this->actingAs(askAdminUser('support'))->get(route('admin.dashboard'))->assertDontSee('data-testid="ask-admin"', false);

        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole('client');

        $this->actingAs($client)->get(route('dashboard'))->assertDontSee('data-testid="ask-admin"', false);
    });

    test('without the key the panel refuses to open and never reaches the model', function (): void {
        AskAdmin::fake()->preventStrayPrompts();
        $support = User::factory()->create(['email_verified_at' => now()]);
        $support->givePermissionTo(['access-admin-panel', 'support.view']);

        Livewire::actingAs($support->refresh())->test('ask.admin')
            ->call('open')->assertForbidden();

        Livewire::actingAs($support)->test('ask.admin')
            ->set('form.question', '¿Cuántos negocios hay?')->call('ask')->assertForbidden();

        AskAdmin::assertNeverPrompted();
    });
});

describe('its tools say exact things', function (): void {
    test('the pulse counts what is in the base, with the screens to act on it', function (): void {
        $first = Business::factory()->create(['name' => 'Panadería Sol']);
        Subscription::factory()->trial()->create(['business_id' => $first->id]);
        Business::factory()->create(['name' => 'Taller Luz']);
        Payment::factory()->create(['business_id' => $first->id]);

        $pulse = asAdminTool(AdminPlatformPulse::class);

        expect($pulse)->toContain('Negocios: 2')
            ->toContain('Comprobantes por verificar: 1')
            ->toContain(route('admin.payments'))
            ->toContain(route('admin.platform-whatsapp'))
            ->toContain('Datos leídos ahora');
    });

    test('the search finds by part of the name, says when nobody matches and ignores a situation it does not know', function (): void {
        $match = Business::factory()->create(['name' => 'Odontológico Sonrisa']);
        Business::factory()->create(['name' => 'Ferretería Norte']);

        $found = asAdminTool(AdminFindBusiness::class, ['search' => 'odontologico']);

        expect($found)->toContain("#{$match->id} Odontológico Sonrisa")
            ->toContain(route('admin.businesses', ['negocio' => $match->id]))
            ->not->toContain('Ferretería Norte')
            ->and(asAdminTool(AdminFindBusiness::class, ['search' => 'zzz']))->toContain('No hay ningún negocio')
            ->and(asAdminTool(AdminFindBusiness::class, ['situation' => 'inventada']))->toContain('Coinciden 2 negocios');
    });

    test('a business file carries its plan, its money and its usage, and never what its customers wrote', function (): void {
        $business = Business::factory()->create(['name' => 'Peluquería Lumen', 'email' => 'lumen@example.com']);
        Subscription::factory()->trial()->create(['business_id' => $business->id]);
        Payment::factory()->paid()->create(['business_id' => $business->id, 'amount' => 79]);
        ConversationMessage::factory()->create(['business_id' => $business->id, 'body' => 'SECRETO-DEL-CLIENTE: mi cédula es 123']);

        $file = asAdminTool(AdminBusinessFile::class, ['business_id' => $business->id]);

        expect($file)->toContain("Negocio #{$business->id}: Peluquería Lumen")
            ->toContain('lumen@example.com')
            ->toContain('Plan:')
            ->toContain('Uso de este mes: 1 de')
            ->toContain('USD 79,00')
            ->toContain(route('admin.businesses', ['negocio' => $business->id]))
            ->not->toContain('SECRETO-DEL-CLIENTE')
            ->and(asAdminTool(AdminBusinessFile::class, ['business_id' => 999999]))->toContain('No existe un negocio');
    });

    test('the alerts come worst first and can be cut by severity or by business', function (): void {
        $business = Business::factory()->create(['name' => 'Kiosco El Faro']);
        $raise = app(RaisePlatformEvent::class);
        $raise->handle(PlatformEventKind::PaymentApproved, $business->id);
        $raise->handle(PlatformEventKind::PaymentPaused, $business->id);

        $all = asAdminTool(AdminAlerts::class);

        expect(strpos($all, 'Crítico'))->toBeLessThan((int) strpos($all, 'Informativo'))
            ->and($all)->toContain('Kiosco El Faro')
            ->and(asAdminTool(AdminAlerts::class, ['severity' => 'info']))->not->toContain('Crítico')
            ->and(asAdminTool(AdminAlerts::class, ['business_id' => 999999]))->toContain('No hay avisos abiertos');
    });

    test('the payment queue lists the oldest receipt first, and says when nothing waits', function (): void {
        expect(asAdminTool(AdminPaymentQueue::class))->toContain('No hay comprobantes');

        $business = Business::factory()->create(['name' => 'Veterinaria Patitas']);
        Payment::factory()->create(['business_id' => $business->id, 'amount' => 29, 'plan' => 'emprende']);

        expect(asAdminTool(AdminPaymentQueue::class))->toContain('Comprobantes por verificar: 1')
            ->toContain('Veterinaria Patitas')
            ->toContain('USD 29,00');
    });
});

describe('the three touches that make it welcome', function (): void {
    test('an alert comes with what the board says to do and the screen that does it', function (): void {
        $business = Business::factory()->create(['name' => 'Kiosco El Faro']);
        app(RaisePlatformEvent::class)->handle(PlatformEventKind::PaymentPaused, $business->id);

        $alerts = asAdminTool(AdminAlerts::class);

        expect($alerts)->toContain('qué hacer: '.__('platform_events.kinds.payment_paused.hint'))
            ->toContain('resolver en: '.route('admin.collections'));
    });

    test('the card of the day counts only what is above zero and only what the key may open', function (): void {
        Payment::factory()->create();
        app(RaisePlatformEvent::class)->handle(PlatformEventKind::PaymentPaused, Business::factory()->create()->id);

        $admin = askAdminUser();

        expect(collect((new AdminAttention($admin))->items)->pluck('count', 'key')->all())->toBe(['receipts' => 1, 'alerts' => 1]);

        $only = User::factory()->create(['email_verified_at' => now()]);
        $only->givePermissionTo(['access-admin-panel', 'ai.ask', 'payments.view']);

        expect(collect((new AdminAttention($only->refresh()))->items)->pluck('key')->all())->toBe(['receipts']);

        $none = User::factory()->create(['email_verified_at' => now()]);
        $none->givePermissionTo(['access-admin-panel', 'ai.ask']);

        expect((new AdminAttention($none->refresh()))->items)->toBe([]);
    });

    test('the panel opens with the card of the day, or says everything is up to date', function (): void {
        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')->call('open');

        $panel->assertSeeHtml('data-testid="ask-admin-today"')->assertSee(__('ask_admin.today.clear'));

        Payment::factory()->count(2)->create();

        Livewire::actingAs(askAdminUser())->test('ask.admin')->call('open')
            ->assertSee(trans_choice('ask_admin.today.receipts', 2, ['count' => 2]))
            ->assertSeeHtml(route('admin.payments'));
    });

    test('the admin\'s home prints the same card as the assistant and the phone, each line leading to its screen', function (): void {
        Payment::factory()->create();
        app(RaisePlatformEvent::class)->handle(PlatformEventKind::PaymentPaused, Business::factory()->create()->id);

        $home = Livewire::actingAs(askAdminUser())->test('admin.home.index')
            ->assertSeeHtml('data-testid="admin-home-today"')
            ->assertSee(__('ask_admin.today.title'))
            ->assertSeeHtml('href="'.route('admin.payments').'"')
            ->assertSeeHtml('href="'.route('admin.alerts').'"');

        // The very lines the assistant and the phone read: one piece, so none of the three can say another thing.
        foreach ((new AdminAttention(askAdminUser()))->links as $line) {
            $home->assertSee(trans_choice('ask_admin.today.'.$line['key'], $line['count'], ['count' => $line['count']]));
        }
    });

    test('a person without the keys of those lines sees the card clear of them, and says so only when nothing waits', function (): void {
        Payment::factory()->create();

        $none = User::factory()->create(['email_verified_at' => now()]);
        $none->givePermissionTo(['access-admin-panel']);

        Livewire::actingAs($none->refresh())->test('admin.home.index')
            ->assertSeeHtml('data-testid="admin-home-today"')
            ->assertDontSee(trans_choice('ask_admin.today.receipts', 1, ['count' => 1]))
            ->assertSee(__('ask_admin.today.clear'));
    });

    test('the Preguntar buttons are drawn for the key holder only, on the home and on every alert', function (): void {
        $business = Business::factory()->create(['name' => 'Taller Luz']);
        app(RaisePlatformEvent::class)->handle(PlatformEventKind::PaymentPaused, $business->id);

        Livewire::actingAs(askAdminUser())->test('admin.home.index')->assertSeeHtml('data-testid="ask-admin-chip"');
        Livewire::actingAs(askAdminUser())->test('admin.alerts.index')
            ->assertSeeHtml('data-testid="ask-admin-chip"')
            ->assertSee(__('ask_admin.ask.alert', ['kind' => PlatformEventKind::PaymentPaused->label(), 'business' => 'Taller Luz']));

        $reader = User::factory()->create(['email_verified_at' => now()]);
        $reader->givePermissionTo(['access-admin-panel', 'alerts.view']);

        Livewire::actingAs($reader->refresh())->test('admin.alerts.index')->assertDontSeeHtml('data-testid="ask-admin-chip"');
    });
});

describe('what each key opens', function (): void {
    test('a tool is only handed to somebody who holds the key of the screen it reads', function (): void {
        $this->seed(AssistantSkillSeeder::class);

        $with = function (array $permissions): array {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $user->givePermissionTo(['access-admin-panel', 'ai.ask', ...$permissions]);

            return collect((new AskAdmin($user->refresh()))->tools())->map(fn ($tool): string => class_basename($tool))->sort()->values()->all();
        };

        expect($with([]))->toBe([])
            ->and($with(['businesses.view']))->toBe(['AdminBusinessFile', 'AdminFindBusiness'])
            ->and($with(['payments.view']))->toBe(['AdminPaymentQueue'])
            ->and($with(['alerts.view']))->toBe(['AdminAlerts'])
            ->and($with(['businesses.view', 'payments.view', 'alerts.view']))->toHaveCount(5)
            ->and(collect((new AskAdmin(askAdminUser()))->tools())->count())->toBe(5);
    });

    test('a link in an answer goes live only when a browser would also read it as ours', function (): void {
        $own = url('/admin/negocios');
        $html = fn (string $url): string => AnswerHtml::of("[ir]({$url})");

        expect($html($own))->toContain('class="ask-link"')
            ->and($html('https://evil.test/x'))->not->toContain('<a')
            ->and($html('https://evil.test\\@'.parse_url($own, PHP_URL_HOST).'/x'))->not->toContain('<a')
            ->and($html('https://'.parse_url($own, PHP_URL_HOST).'@evil.test/x'))->not->toContain('<a')
            ->and($html('https://'.parse_url($own, PHP_URL_HOST).'.evil.test/x'))->not->toContain('<a')
            ->and($html('javascript:alert(1)'))->not->toContain('<a');
    });
});

describe('what it knows', function (): void {
    test('it is built from the admin skills, in the agent instructions order the contract demands', function (): void {
        $this->seed(AssistantSkillSeeder::class);
        $agent = new AskAdmin(askAdminUser());
        $contract = AssistantContract::for(null, app()->getLocale());

        $instructions = trim((string) $agent->instructions());
        $tools = collect($agent->tools());

        expect($tools)->toHaveCount(5)
            ->and($tools->every(fn ($tool): bool => $tool instanceof AdminSkillTool))->toBeTrue()
            ->and(str_starts_with($instructions, trim($contract->grounding)))->toBeTrue()
            ->and(str_ends_with($instructions, trim($contract->clock)))->toBeTrue()
            ->and($instructions)->toContain('Le hablás a Juan, quien administra')->not->toContain('Pablo');
    });

    test('every screen of the admin menu has its guide, and every guide reads real texts', function (): void {
        $this->seed(MenuSeeder::class);

        $routes = Menu::query()->where('panel', 'admin')->whereNotNull('route_name')->pluck('route_name');
        $guide = (array) config('atendia.admin_assistant.guide');

        expect($routes->reject(fn (string $route): bool => array_key_exists($route, $guide))->values()->all())->toBe([]);

        $empty = collect($guide)->merge((array) config('atendia.owner_assistant.guide'))
            ->filter(fn (array $texts): bool => trim(ScreenTexts::of($texts[1])) === '' || __($texts[0]) === $texts[0])
            ->keys()->all();

        expect($empty)->toBe([]);
    });

    test('both panels become uniquely named skills the model loads on demand', function (): void {
        $this->seed(MenuSeeder::class);

        $names = collect((new AskAdmin(askAdminUser()))->skills())->map(fn ($skill): string => $skill->name);

        expect($names->duplicates()->all())->toBe([])
            ->and($names->contains('admin-dashboard'))->toBeTrue()
            ->and($names->contains('admin-ai-usage'))->toBeTrue()
            ->and($names->contains('cliente-my-business'))->toBeTrue()
            ->and($names->count())->toBe(count(config('atendia.admin_assistant.guide')) + count(config('atendia.owner_assistant.guide')));

        $business = collect((new AskAdmin(askAdminUser()))->skills())->firstWhere('name', 'admin-businesses');

        expect((string) $business->instructions)->toContain(route('admin.businesses'))
            ->and((string) $business->description)->toContain('Negocios › Todos');
    });
});

describe('the panel', function (): void {
    test('a question gets its answer, and the thread keeps the conversation it joined', function (): void {
        AskAdmin::fake(['Hay 3 negocios.']);

        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->call('open')
            ->set('form.question', '¿Cuántos negocios hay?')
            ->call('ask');

        expect($panel->get('messages'))->toBe([
            ['role' => 'owner', 'text' => '¿Cuántos negocios hay?'],
            ['role' => 'ai', 'text' => 'Hay 3 negocios.'],
        ]);

        AskAdmin::assertPrompted('¿Cuántos negocios hay?');

        $stored = $panel->get('conversationId');

        expect($stored)->toBeString()
            ->and(DB::table('agent_conversations')->where('id', $stored)->count())->toBe(1)
            ->and(DB::table('agent_conversation_messages')->where('conversation_id', $stored)->pluck('role')->all())->toBe(['user', 'assistant']);
    });

    test('a conversation id forged from the browser cannot join somebody else\'s thread', function (): void {
        AskAdmin::fake(['Respuesta A.', 'Respuesta B.']);

        $theirs = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->set('form.question', 'dato privado de la otra admin')->call('ask')->get('conversationId');

        $mine = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->set('conversationId', $theirs)
            ->set('form.question', 'intento entrar')->call('ask');

        expect($mine->get('conversationId'))->not->toBe($theirs)->not->toBeNull()
            ->and(DB::table('agent_conversation_messages')->where('conversation_id', $theirs)->count())->toBe(2)
            ->and(DB::table('agent_conversations')->count())->toBe(2);
    });

    test('the next question continues the same stored conversation instead of opening another', function (): void {
        AskAdmin::fake(['Uno.', 'Dos.']);

        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->set('form.question', 'primera')->call('ask');
        $first = $panel->get('conversationId');

        $panel->set('form.question', 'segunda')->call('ask');

        expect($panel->get('conversationId'))->toBe($first)
            ->and(DB::table('agent_conversations')->count())->toBe(1)
            ->and(DB::table('agent_conversation_messages')->where('conversation_id', $first)->count())->toBe(4);
    });

    test('a model that fails answers with the notice and the panel keeps working', function (): void {
        AskAdmin::fake([fn () => throw new RuntimeException('provider down')]);

        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->set('form.question', 'hola')->call('ask');

        expect($panel->get('messages')[1])->toBe(['role' => 'ai', 'text' => __('ask_admin.failed'), 'failed' => true]);
    });

    test('the daily ceiling counts only the admin side today and closes the input when it is reached', function (): void {
        AskAdmin::fake()->preventStrayPrompts();
        config()->set('atendia.admin_assistant.daily_questions', 2);

        $row = ['model' => 'gpt-6-luna', 'input_tokens' => 0, 'cached_tokens' => 0, 'output_tokens' => 0];
        AiUsage::query()->create([...$row, 'kind' => AiUsage::ASK_ADMIN]);
        AiUsage::query()->create([...$row, 'kind' => AiUsage::ASK_ATENDIA]);

        expect(AiUsage::adminQuestionsToday())->toBe(1);

        AiUsage::query()->create([...$row, 'kind' => AiUsage::ASK_ADMIN]);

        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->call('open')->assertSee(__('ask_admin.used_up', ['cap' => 2]))
            ->set('form.question', 'una más')->call('ask');

        expect($panel->get('messages'))->toBe([['role' => 'ai', 'text' => __('ask_admin.used_up', ['cap' => 2]), 'failed' => true]]);
        AskAdmin::assertNeverPrompted();
    });

    test('a new chat clears the thread without erasing anything stored', function (): void {
        AskAdmin::fake(['Listo.']);

        $panel = Livewire::actingAs(askAdminUser())->test('ask.admin')
            ->set('form.question', 'hola')->call('ask')->call('newChat');

        expect($panel->get('messages'))->toBe([])->and($panel->get('conversationId'))->toBeNull();
    });
});
