<?php

declare(strict_types=1);

use App\Enums\AdoptionMarkKind;
use App\Enums\AdoptionSituation;
use App\Enums\AdoptionStep;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Mail\StalledAdoptionReport;
use App\Models\AdoptionMark;
use App\Models\AdoptionNudge;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LoginActivity;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\AdoptionNudgeSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Adoption
|--------------------------------------------------------------------------
| The screen reads a trail that already exists and reports the FURTHEST step
| each account reached. Nothing is recorded for it, so what is worth testing
| is that the ladder is read in the right order and that the screen stays
| shut for a client.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** An owner account with its own business: the shape the funnel walks. */
function owner(string $email, array $business = []): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->business()->associate(Business::factory()->create($business))->save();

    return $user;
}

test('an account that never finished the wizard sits on the first step', function (): void {
    User::factory()->create(['email' => 'sola@negocio.test']);

    $row = User::adoptionRows()->sole();

    expect($row->step)->toBe(AdoptionStep::Registered)
        ->and($row->business)->toBeNull()
        ->and($row->isStalled)->toBeTrue();
});

test('the step is the furthest one reached, not the last thing that happened', function (): void {
    // Catalog loaded with WhatsApp never connected: the row has to report the
    // step it got to, and the ladder is not the order events arrived in.
    $user = owner('catalogo@negocio.test');
    Service::factory()->create(['business_id' => $user->business_id, 'name' => 'Ecodoppler']);

    expect(User::adoptionRows()->sole()->step)->toBe(AdoptionStep::CatalogLoaded);
});

test('a business whose assistant answered reaches the end of the ladder', function (): void {
    $user = owner('completa@negocio.test', ['whatsapp_connected_at' => now()->subDay()]);
    $conversation = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->create([
        'business_id' => $user->business_id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Assistant,
    ]);

    $row = User::adoptionRows()->sole();

    expect($row->step)->toBe(AdoptionStep::AssistantAnswered)
        ->and($row->isStalled)->toBeFalse()
        ->and($row->conversations)->toBe(1);
});

test('a message written by a human is not the assistant answering', function (): void {
    $user = owner('humana@negocio.test', ['whatsapp_connected_at' => now()]);
    $conversation = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->create([
        'business_id' => $user->business_id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Human,
    ]);

    expect(User::adoptionRows()->sole()->step)->toBe(AdoptionStep::FirstConversation);
});

test('days away come from the last sign-in, and never coming back is said', function (): void {
    $came = owner('volvio@negocio.test');
    LoginActivity::factory()->create(['user_id' => $came->id, 'created_at' => now()->subDays(6)]);
    owner('nunca@negocio.test');

    $rows = User::adoptionRows()->keyBy('email');

    expect($rows['volvio@negocio.test']->daysIdle)->toBe(6)
        ->and($rows['nunca@negocio.test']->daysIdle)->toBeNull();
});

test('the rows carry the reports each business opened', function (): void {
    $user = owner('reporta@negocio.test');
    SupportTicket::factory()->count(2)->create([
        'business_id' => $user->business_id,
        'user_id' => $user->id,
    ]);

    expect(User::adoptionRows()->sole()->tickets)->toBe(2);
});

test('the screen says where each account stopped', function (): void {
    $this->seed(MenuSeeder::class);
    owner('primera@negocio.test');
    $second = owner('segunda@negocio.test');
    Service::factory()->create(['business_id' => $second->business_id]);

    $admin = User::factory()->create(['email' => 'equipo@atendia.test']);
    $admin->syncRoles(['admin']);

    // Both accounts were created just now: inside the plazo they are starting, not stalled.
    Livewire::actingAs($admin)
        ->test('admin.adoption.index')
        ->assertSet('view', 'stalled')
        ->call('show', 'starting')
        ->assertSee('Creó el negocio')
        ->assertSee('Cargó su catálogo')
        ->assertSee('primera@negocio.test')
        ->assertSee('segunda@negocio.test');
});

test('the legs of the funnel are measured only where both ends happened', function (): void {
    $user = owner('tramo@negocio.test');
    $user->business->forceFill(['created_at' => now()->subDays(5)])->save();
    Service::factory()->create([
        'business_id' => $user->business_id,
        'created_at' => now()->subDay(),
    ]);

    $row = User::adoptionRows()->sole();

    expect($row->daysBetween(AdoptionStep::BusinessCreated, AdoptionStep::CatalogLoaded))->toBe(4)
        ->and($row->daysBetween(AdoptionStep::CatalogLoaded, AdoptionStep::WhatsAppConnected))->toBeNull();
});

/**
 * The same plazo on every step, so a test says what it is about.
 *
 * @return array<string, int>
 */
function adoptionLimits(int $days): array
{
    return collect(AdoptionStep::cases())->mapWithKeys(fn (AdoptionStep $step): array => [$step->value => $days])->all();
}

/** An admin on the screen. */
function adoptionAdmin(): User
{
    $admin = User::factory()->create(['email' => 'equipo@atendia.test']);
    $admin->syncRoles(['admin']);

    return $admin;
}

test('an account is starting inside the plazo of its step, stalled past it, active once answered', function (): void {
    config()->set('atendia.adoption.stall_days', adoptionLimits(7));

    $young = User::factory()->create(['email' => 'joven@negocio.test', 'created_at' => now()->subDays(2)]);
    $old = User::factory()->create(['email' => 'vieja@negocio.test', 'created_at' => now()->subDays(20)]);

    $rows = User::adoptionRows()->keyBy('email');

    expect($rows['joven@negocio.test']->situation)->toBe(AdoptionSituation::Starting)
        ->and($rows['vieja@negocio.test']->situation)->toBe(AdoptionSituation::Stalled)
        ->and($rows['vieja@negocio.test']->daysInStep)->toBe(20);

    // The plazo is a platform setting: turn it down and the young one stalls too.
    config()->set('atendia.adoption.stall_days', adoptionLimits(1));

    expect(User::adoptionRows()->keyBy('email')['joven@negocio.test']->situation)->toBe(AdoptionSituation::Stalled);
});

test('the plazo counts from the moment the account reached its step, not from sign-up', function (): void {
    config()->set('atendia.adoption.stall_days', adoptionLimits(7));

    $user = owner('paso@negocio.test');
    $user->forceFill(['created_at' => now()->subDays(30)])->save();
    $user->business->forceFill(['created_at' => now()->subDays(2)])->save();

    // Registered a month ago, but the business only two days ago: it is not stuck.
    expect(User::adoptionRows()->sole()->situation)->toBe(AdoptionSituation::Starting);
});

test('the funnel counts who got at least that far and who stopped right there', function (): void {
    $this->seed(MenuSeeder::class);
    User::factory()->create(['email' => 'a@negocio.test']);
    owner('b@negocio.test');
    $third = owner('c@negocio.test');
    Service::factory()->create(['business_id' => $third->business_id]);

    $flow = Livewire::actingAs(adoptionAdmin())->test('admin.adoption.index')->get('flow');

    expect($flow->pluck('reached')->all())->toBe([3, 2, 1, 0, 0, 0])
        ->and($flow->pluck('stopped')->all())->toBe([1, 1, 1, 0, 0, null]);
});

test('an account with a business opens its file, and one without it opens the mail written for its step', function (): void {
    $this->seed(MenuSeeder::class);
    $this->seed(AdoptionNudgeSeeder::class);
    config()->set('atendia.adoption.stall_days', adoptionLimits(1));

    $with = owner('con@negocio.test', ['name' => 'Panadería Sol']);
    $with->forceFill(['created_at' => now()->subDays(10)])->save();
    $with->business->forceFill(['created_at' => now()->subDays(10)])->save();
    User::factory()->create(['name' => 'Lara Aguirre', 'email' => 'lara@negocio.test', 'created_at' => now()->subDays(10)]);

    $html = Livewire::actingAs(adoptionAdmin())->test('admin.adoption.index')->html();

    expect($html)
        ->toContain(route('admin.businesses', ['negocio' => $with->business_id]))
        ->toContain('mailto:lara%40negocio.test')
        // The placeholder in the written text became her name.
        ->toContain(rawurlencode('Hola Lara Aguirre'))
        ->not->toContain('{nombre}');
});

test('with the message switched off there is no mail to open, rather than an empty one', function (): void {
    $this->seed(MenuSeeder::class);
    $this->seed(AdoptionNudgeSeeder::class);
    config()->set('atendia.adoption.stall_days', adoptionLimits(1));
    AdoptionNudge::query()->update(['is_active' => false]);

    User::factory()->create(['email' => 'lara@negocio.test', 'created_at' => now()->subDays(10)]);

    expect(Livewire::actingAs(adoptionAdmin())->test('admin.adoption.index')->html())->not->toContain('mailto:');
});

test('an unknown tab in the link lands on the one that asks for action', function (): void {
    $this->seed(MenuSeeder::class);
    // The tabs only exist once there is an account to put in one.
    User::factory()->create(['email' => 'una@negocio.test']);

    Livewire::actingAs(adoptionAdmin())
        ->test('admin.adoption.index', ['view' => 'nonsense'])
        ->assertSee('Nadie trabado');
});

/** The mail goes to the admin account named in the config. */
function adoptionAlertSetup(): void
{
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    config()->set('atendia.adoption.stall_days', adoptionLimits(7));
    User::factory()->create(['email' => 'equipo@atendia.test'])->syncRoles(['admin']);
}

test('the team is mailed the accounts that passed the plazo of their step, by the same rule as the screen', function (): void {
    adoptionAlertSetup();

    // Nine days on a step with a seven-day plazo: stalled, whether or not she logged in.
    $stalled = owner('trabada@negocio.test');
    $stalled->forceFill(['created_at' => now()->subDays(20)])->save();
    $stalled->business->forceFill(['created_at' => now()->subDays(9)])->save();

    // Same business age but inside the plazo: still starting, not reported.
    $starting = owner('arrancando@negocio.test');
    $starting->business->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertQueued(StalledAdoptionReport::class, fn (StalledAdoptionReport $mail): bool => count($mail->rows) === 1
        && $mail->rows[0]->email === 'trabada@negocio.test');
});

test('an account is told once per step: the next morning is silent, the next step starts over', function (): void {
    adoptionAlertSetup();

    $user = owner('una-vez@negocio.test');
    $user->forceFill(['created_at' => now()->subDays(30)])->save();
    $user->business->forceFill(['created_at' => now()->subDays(12)])->save();

    $this->artisan('atendia:adoption-alert')->assertSuccessful();
    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertQueued(StalledAdoptionReport::class, 1);

    // It moves one step on and sits there past the plazo: that is news again.
    Service::factory()->create(['business_id' => $user->business_id, 'created_at' => now()->subDays(9)]);

    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertQueued(StalledAdoptionReport::class, 2);
});

test('an account that answered is never reported', function (): void {
    adoptionAlertSetup();

    $user = owner('contesta@negocio.test', ['whatsapp_connected_at' => now()->subDays(30)]);
    $user->forceFill(['created_at' => now()->subDays(40)])->save();
    $conversation = Conversation::factory()->create(['business_id' => $user->business_id]);
    ConversationMessage::factory()->create([
        'business_id' => $user->business_id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Assistant,
        'created_at' => now()->subDays(25),
    ]);

    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('each step has its own plazo', function (): void {
    config()->set('atendia.adoption.stall_days', ['registered' => 3, 'business' => 30, 'catalog' => 7, 'whatsapp' => 10, 'conversation' => 7]);

    // Five days since sign-up: stalled at the first step (3), not at the second (30).
    User::factory()->create(['email' => 'registro@negocio.test', 'created_at' => now()->subDays(5)]);
    $made = owner('negocio@negocio.test');
    $made->forceFill(['created_at' => now()->subDays(5)])->save();
    $made->business->forceFill(['created_at' => now()->subDays(5)])->save();

    $rows = User::adoptionRows()->keyBy('email');

    expect($rows['registro@negocio.test']->situation)->toBe(AdoptionSituation::Stalled)
        ->and($rows['registro@negocio.test']->stallLimit)->toBe(3)
        ->and($rows['negocio@negocio.test']->situation)->toBe(AdoptionSituation::Starting)
        ->and($rows['negocio@negocio.test']->stallLimit)->toBe(30);
});

test('writing to an account is noted, and the note stays with the step it was written on', function (): void {
    $this->seed(MenuSeeder::class);
    $this->seed(AdoptionNudgeSeeder::class);
    config()->set('atendia.adoption.stall_days', adoptionLimits(1));

    $user = User::factory()->create(['email' => 'lara@negocio.test', 'created_at' => now()->subDays(10)]);
    $admin = adoptionAdmin();

    $screen = Livewire::actingAs($admin)->test('admin.adoption.index');
    $screen->assertDontSee('Le escribiste')
        ->call('markWritten', 'lara@negocio.test')
        ->assertSee('Le escribiste');

    expect(AdoptionMark::query()->sole())
        ->user_id->toBe($user->id)
        ->step->toBe(AdoptionStep::Registered)
        ->kind->toBe(AdoptionMarkKind::Written)
        ->created_by->toBe($admin->id);

    // She moves on: the account now sits on the next step and the old note stops counting.
    $user->business()->associate(Business::factory()->create(['created_at' => now()->subDays(5)]))->save();
    Livewire::actingAs($admin)->test('admin.adoption.index')->assertDontSee('Le escribiste');
});

test('the note says "a moment ago" rather than "0 seconds", and names the other person when it was not her', function (): void {
    $this->seed(MenuSeeder::class);
    config()->set('atendia.adoption.stall_days', adoptionLimits(1));

    $user = User::factory()->create(['email' => 'lara@negocio.test', 'created_at' => now()->subDays(10)]);
    $other = User::factory()->create(['name' => 'Rocío Paz', 'email' => 'rocio@atendia.test']);
    $other->syncRoles(['admin']);

    AdoptionMark::record($user->email, AdoptionStep::Registered, AdoptionMarkKind::Written, $other->id);

    Livewire::actingAs(adoptionAdmin())->test('admin.adoption.index')
        ->assertSee('Le escribió Rocío Paz hace un momento')
        ->assertDontSee('0 segundos');

    // And when it was her own hand, it says "you".
    Livewire::actingAs($other)->test('admin.adoption.index')->assertSee('Le escribiste hace un momento');
});

test('the daily mail takes her straight to the stalled tab', function (): void {
    $user = owner('x@negocio.test');
    $mail = new StalledAdoptionReport($user, User::adoptionRows()->all());

    $mail->assertSeeInHtml('ver=stalled');
});

test('only an account on the screen can be marked', function (): void {
    $this->seed(MenuSeeder::class);
    User::factory()->create(['email' => 'real@negocio.test']);

    Livewire::actingAs(adoptionAdmin())->test('admin.adoption.index')->call('markWritten', 'inventado@negocio.test');

    expect(AdoptionMark::query()->count())->toBe(0);
});

test('a client cannot open the adoption screen', function (): void {
    $this->seed(MenuSeeder::class);
    $client = owner('cliente@negocio.test');

    $this->actingAs($client)->get(route('admin.adoption'))->assertForbidden();
});
