<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketOpened;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\EvolutionApi;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support from inside the panel
|--------------------------------------------------------------------------
| One required field. The screen, the url and the browser are CAPTURED, never
| asked, which is the whole reason the form can be that short. The team hears
| about it by mail and by WhatsApp, and neither notice can take the ticket down.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    config()->set('services.evolution.instance', 'atendia-demo');
    config()->set('atendia.sales_whatsapp', '+58 414 000 11 22');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $this->user = User::factory()->create(['name' => 'María González']);
    $this->user->business()->associate($this->business)->save();

    $this->whatsapp = mock(EvolutionApi::class);
    $this->whatsapp->shouldReceive('sendText')->andReturnTrue()->byDefault();
    app()->instance(EvolutionApi::class, $this->whatsapp);
});

test('one field is enough, and the screen comes captured', function (): void {
    Mail::fake();

    Livewire::actingAs($this->user)
        ->test('support.widget', ['from' => 'my-products'])
        ->call('open')
        ->assertSet('form.screen', 'my-products')
        ->set('client', ['url' => 'https://atendia.test/productos', 'viewport' => '1280x900', 'agent' => 'Chromium'])
        ->set('form.body', 'El botón de guardar no hace nada cuando el precio está vacío')
        ->call('send')
        ->assertSet('sentCode', fn (?string $code): bool => $code !== null && str_starts_with($code, 'ATN-'));

    $ticket = SupportTicket::query()->firstOrFail();

    expect($ticket->screen)->toBe('my-products')
        ->and($ticket->kind)->toBe(SupportTicketKind::Problem)
        ->and($ticket->status)->toBe(SupportTicketStatus::New)
        ->and($ticket->context['url'])->toBe('https://atendia.test/productos')
        ->and($ticket->context['viewport'])->toBe('1280x900')
        // Never asked, always known.
        ->and($ticket->context['locale'])->toBe('es');
});

test('a body under ten characters is refused, because it tells us nothing', function (): void {
    Mail::fake();

    Livewire::actingAs($this->user)
        ->test('support.widget')
        ->call('open')
        ->set('form.body', 'no va')
        ->call('send')
        ->assertHasErrors(['body']);

    expect(SupportTicket::query()->count())->toBe(0);
});

test('the team hears about it by mail and by WhatsApp', function (): void {
    Mail::fake();

    $this->whatsapp->shouldReceive('sendText')
        ->once()
        ->withArgs(fn (string $instance, string $number, string $text): bool => str_contains($text, 'ATN-'));

    Livewire::actingAs($this->user)
        ->test('support.widget', ['from' => 'my-products'])
        ->call('open')
        ->set('form.body', 'El botón de guardar no hace nada con el precio vacío')
        ->call('send');

    Mail::assertQueued(SupportTicketOpened::class);
});

test('a notice that fails never takes the report down with it', function (): void {
    Mail::fake();

    $this->whatsapp->shouldReceive('sendText')->andThrow(new RuntimeException('evolution down'));

    Livewire::actingAs($this->user)
        ->test('support.widget')
        ->call('open')
        ->set('form.body', 'La pantalla de pagos queda en blanco al entrar')
        ->call('send')
        ->assertSet('sentCode', fn (?string $code): bool => $code !== null);

    expect(SupportTicket::query()->count())->toBe(1);
});

test('opened from nowhere in particular it asks where, instead of guessing', function (): void {
    $this->seed(MenuSeeder::class);
    $this->user->assignRole('client');

    Livewire::actingAs($this->user->refresh())
        ->test('support.widget', ['from' => null])
        ->call('open')
        ->assertSet('form.screen', null)
        // The selector is the menu itself, so a screen born tomorrow is there.
        ->assertSee(__('support.screen_placeholder'));
});

test('the code is read out loud, so it carries no letter that looks like a digit', function (): void {
    foreach (range(1, 40) as $ignored) {
        expect(SupportTicket::freshCode())->toMatch('/^ATN-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/');
    }
});

test('the admin inbox answers by arrival and the settled ones sink', function (): void {
    $old = SupportTicket::factory()->for($this->business)->create(['created_at' => now()->subDays(3)]);
    $new = SupportTicket::factory()->for($this->business)->create(['created_at' => now()->subHour()]);
    $done = SupportTicket::factory()->resolved()->for($this->business)->create(['created_at' => now()->subDays(9)]);

    $order = SupportTicket::inbox('all')->pluck('id');

    expect($order->all())->toBe([$old->id, $new->id, $done->id]);

    expect(SupportTicket::inbox('open')->pluck('id')->all())->toBe([$old->id, $new->id]);
});

test('the admin moves a ticket along and the resolved one gets its date', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $ticket = SupportTicket::factory()->for($this->business)->create();

    Livewire::actingAs($admin)
        ->test('admin.support.index')
        ->call('setStatus', $ticket->id, 'resolved')
        ->assertDispatched('notify');

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();
});

test('the admin answers from the inbox and the reply travels to her WhatsApp', function (): void {
    // The owner's own alert number, which is the one a reply goes to.
    $this->business->update(['fallback_whatsapp_number' => '+584140001122']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $ticket = SupportTicket::factory()->for($this->business)->create();

    $this->whatsapp->shouldReceive('sendText')
        ->once()
        ->withArgs(fn (string $instance, string $number, string $text): bool => str_contains($text, 'Lo miramos y ya está'));

    Livewire::actingAs($admin)
        ->test('admin.support.index')
        ->call('toggle', $ticket->id)
        ->set('reply', 'Lo miramos y ya está arreglado en tu panel')
        ->call('answer', $ticket->id)
        ->assertSet('reply', '');

    $ticket->refresh();

    expect($ticket->reply)->toBe('Lo miramos y ya está arreglado en tu panel')
        ->and($ticket->answered_at)->not->toBeNull()
        // The ball is hers now, and the status says exactly that.
        ->and($ticket->status)->toBe(SupportTicketStatus::Waiting);
});

test('resolving it rings the bell of the business that reported it', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $ticket = SupportTicket::factory()->for($this->business)->create();

    Livewire::actingAs($admin)
        ->test('admin.support.index')
        ->call('setStatus', $ticket->id, 'resolved');

    $notification = PanelNotification::query()->withoutGlobalScopes()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->business_id)->toBe($this->business->id)
        ->and($notification->type)->toBe(PanelNotificationType::SupportResolved);
});

test('the inbox shows which screens are reported most', function (): void {
    SupportTicket::factory()->count(3)->for($this->business)->create(['screen' => 'my-products']);
    SupportTicket::factory()->for($this->business)->create(['screen' => 'conversations']);
    SupportTicket::factory()->resolved()->for($this->business)->create(['screen' => 'my-products']);

    $this->actingAs($this->user);

    $points = SupportTicket::painPoints();

    // Only what is still open counts: a settled report is not a pain any more.
    expect($points->first()->screen)->toBe('my-products')
        ->and($points->first()->total)->toBe(3);
});

test('the support inbox is the admin panel, not the client one', function (): void {
    $this->actingAs($this->user)->get('/admin/soporte')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/soporte')->assertOk();
});

test('one business never reads another business report', function (): void {
    $other = Business::factory()->create();
    SupportTicket::factory()->for($other)->create(['body' => 'Reporte ajeno']);
    SupportTicket::factory()->for($this->business)->create(['body' => 'Reporte propio']);

    $this->actingAs($this->user);

    $bodies = SupportTicket::inbox('all')->pluck('body');

    expect($bodies)->toContain('Reporte propio')
        ->and($bodies)->not->toContain('Reporte ajeno');
});
