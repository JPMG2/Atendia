<?php

declare(strict_types=1);

use App\Enums\AdoptionStep;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Mail\StalledAdoptionReport;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LoginActivity;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
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

    Livewire::actingAs($admin)
        ->test('admin.adoption')
        ->assertSet('filter', 'stalled')
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

test('the team is mailed the accounts that went quiet halfway', function (): void {
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    User::factory()->create(['email' => 'equipo@atendia.test'])->syncRoles(['admin']);

    $quiet = owner('callada@negocio.test');
    LoginActivity::factory()->create(['user_id' => $quiet->id, 'created_at' => now()->subDays(9)]);

    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertQueued(StalledAdoptionReport::class, fn (StalledAdoptionReport $mail): bool => count($mail->rows) === 1);
});

test('an account still inside the window is not reported', function (): void {
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    User::factory()->create(['email' => 'equipo@atendia.test'])->syncRoles(['admin']);

    $recent = owner('reciente@negocio.test');
    LoginActivity::factory()->create(['user_id' => $recent->id, 'created_at' => now()->subDays(2)]);

    $this->artisan('atendia:adoption-alert')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('a client cannot open the adoption screen', function (): void {
    $this->seed(MenuSeeder::class);
    $client = owner('cliente@negocio.test');

    $this->actingAs($client)->get(route('admin.adoption'))->assertForbidden();
});
