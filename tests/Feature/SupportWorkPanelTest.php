<?php

declare(strict_types=1);

use App\Actions\Support\AddSupportNote;
use App\Actions\Support\AnswerSupportTicket;
use App\Classes\Main\SupportCustomer;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportDelivery;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketAnswered;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\EvolutionApi;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support is a work panel, not a list
|--------------------------------------------------------------------------
| A report is solved when the business has the answer in hand, or it is left
| saying exactly what is missing. What this pins: an answer that reached
| nobody never reads as an answered report, a note never leaves the team,
| and a report that could not be solved carries what to do next.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.support.overdue_hours', 24);
    config()->set('atendia.support.overdue_hours_high', 8);
    config()->set('services.evolution.instance', 'atendia-demo');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida', 'fallback_whatsapp_number' => '+584140001122', 'billing_email' => 'duena@clinicavida.test', 'email' => null]);

    $admin = User::factory()->create(['name' => 'Rocío Paz', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->admin = $admin->refresh();

    $this->whatsapp = mock(EvolutionApi::class);
    app()->instance(EvolutionApi::class, $this->whatsapp);
});

function workTicket(array $attributes = []): SupportTicket
{
    return SupportTicket::factory()->for(test()->business)->create($attributes);
}

test('an answer by WhatsApp is written down, moves the report to the business and takes it in hand', function (): void {
    $this->whatsapp->shouldReceive('sendText')->once()->andReturnTrue();
    $ticket = workTicket();

    $delivery = app(AnswerSupportTicket::class)->reply($ticket, 'Ya lo revisamos', $this->admin);

    expect($delivery)->toBe(SupportDelivery::WhatsApp)
        ->and($ticket->refresh()->status)->toBe(SupportTicketStatus::Waiting)
        ->and($ticket->reply)->toBe('Ya lo revisamos')
        ->and($ticket->assigned_to)->toBe($this->admin->id)
        ->and(SupportTicketMessage::query()->sole())
        ->body->toBe('Ya lo revisamos')
        ->delivery->toBe(SupportDelivery::WhatsApp)
        ->is_internal->toBeFalse();
});

test('with no WhatsApp number the answer goes by mail to whoever reported it', function (): void {
    Mail::fake();
    $this->business->update(['fallback_whatsapp_number' => null]);
    $reporter = User::factory()->create(['email' => 'maria@clinicavida.test']);
    $ticket = workTicket(['user_id' => $reporter->id]);

    $this->whatsapp->shouldNotReceive('sendText');

    $delivery = app(AnswerSupportTicket::class)->reply($ticket, 'Te lo respondemos por aquí', $this->admin);

    expect($delivery)->toBe(SupportDelivery::Email)
        ->and($ticket->refresh()->status)->toBe(SupportTicketStatus::Waiting);

    Mail::assertQueued(SupportTicketAnswered::class, fn (SupportTicketAnswered $mail): bool => $mail->hasTo('maria@clinicavida.test') && $mail->reply === 'Te lo respondemos por aquí');
});

test('an answer that reached nobody is kept, said so, and does not move the report', function (): void {
    $this->business->update(['fallback_whatsapp_number' => null, 'billing_email' => '']);
    $ticket = workTicket(['user_id' => null]);

    $delivery = app(AnswerSupportTicket::class)->reply($ticket, 'Esto no va a llegar', $this->admin);

    expect($delivery)->toBe(SupportDelivery::NoContact)
        // The ball did not change sides: nobody has it but us.
        ->and($ticket->refresh()->status)->toBe(SupportTicketStatus::New)
        ->and($ticket->reply)->toBeNull()
        // But what was written is not lost, and says how it left.
        ->and(SupportTicketMessage::query()->sole()->delivery)->toBe(SupportDelivery::NoContact);
});

test('a failed WhatsApp falls back to the mail, and with no mail it says it failed', function (): void {
    Mail::fake();
    $this->business->update(['billing_email' => '']);
    $this->whatsapp->shouldReceive('sendText')->andThrow(new RuntimeException('bridge down'));

    $withMail = workTicket(['user_id' => User::factory()->create(['email' => 'a@b.test'])->id]);
    $without = workTicket(['user_id' => null]);

    expect(app(AnswerSupportTicket::class)->reply($withMail, 'Uno', $this->admin))->toBe(SupportDelivery::Email)
        ->and(app(AnswerSupportTicket::class)->reply($without, 'Dos', $this->admin))->toBe(SupportDelivery::Failed)
        ->and($without->refresh()->status)->toBe(SupportTicketStatus::New);
});

test('the screen says the answer did not leave instead of announcing it as sent', function (): void {
    $this->business->update(['fallback_whatsapp_number' => null, 'billing_email' => '']);
    $ticket = workTicket(['user_id' => null]);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('reply.body', 'Respuesta que no sale')
        ->call('sendReply')
        ->assertSee('No se entregó: este negocio no tiene WhatsApp para avisos ni correo')
        // The text stays, so it can be resent by another road.
        ->assertSet('reply.body', 'Respuesta que no sale');
});

test('an internal note goes nowhere: no send, no status change, and the thread never shows it', function (): void {
    $this->whatsapp->shouldNotReceive('sendText');
    Mail::fake();
    $ticket = workTicket();

    app(AddSupportNote::class)->handle($ticket, 'Reproducido: es el job de análisis', $this->admin);

    $note = SupportTicketMessage::query()->sole();

    expect($note->is_internal)->toBeTrue()
        ->and($note->delivery)->toBeNull()
        ->and($ticket->refresh()->status)->toBe(SupportTicketStatus::New);

    Mail::assertNothingQueued();

    $screen = Livewire::actingAs($this->admin)->test('admin.support.index')->call('openTicket', $ticket->id);

    $screen->assertDontSee('Reproducido: es el job de análisis')
        ->call('showPanel', 'notes')
        ->assertSee('Reproducido: es el job de análisis')
        ->assertSee('Solo las ve el equipo');
});

test('a note needs words, and saves with the author', function (): void {
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('note.body', '')
        ->call('saveNote')
        ->assertHasErrors('body')
        ->set('note.body', 'Lo vi en los logs de las 23:10')
        ->call('saveNote')
        ->assertHasNoErrors()
        ->assertSet('note.body', '');

    expect(SupportTicketMessage::query()->sole()->user_id)->toBe($this->admin->id);
});

test('a report that cannot be solved is blocked with what was tried, what is missing, who follows and by when', function (): void {
    $this->whatsapp->shouldReceive('sendText')->once()->andReturnTrue();
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->call('startBlock')
        ->set('block.tried', 'Reproducido a las 23:10: la cola se llena')
        ->set('block.missing', 'Que Desarrollo separe la cola del asistente')
        ->set('block.owner', 'Desarrollo')
        ->set('block.due', now()->addDays(3)->toDateString())
        ->set('block.notice', 'Lo estamos revisando con el equipo técnico.')
        ->call('saveBlock')
        ->assertHasNoErrors()
        ->assertSet('queue', 'blocked');

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Blocked)
        ->and($ticket->blocked_missing)->toBe('Que Desarrollo separe la cola del asistente')
        ->and($ticket->blocked_owner)->toBe('Desarrollo')
        ->and($ticket->blocked_due->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and($ticket->assigned_to)->toBe($this->admin->id)
        // The notice reached the business, and the report is still blocked, not "waiting".
        ->and(SupportTicketMessage::query()->sole()->delivery)->toBe(SupportDelivery::WhatsApp);
});

test('blocking without saying what is missing, or with a date already gone, is refused', function (): void {
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->call('startBlock')
        ->set('block.tried', 'corto')
        ->set('block.missing', '')
        ->set('block.due', now()->subDay()->toDateString())
        ->call('saveBlock')
        ->assertHasErrors(['tried', 'missing', 'due']);

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::New);
});

test('a blocked report shows what is missing in the queue, and is late once its date passes', function (): void {
    $ticket = workTicket(['status' => SupportTicketStatus::Blocked, 'blocked_tried' => 'Probado', 'blocked_missing' => 'Que Desarrollo revise el límite de la cola', 'blocked_owner' => 'Desarrollo', 'blocked_due' => today()->subDays(2), 'blocked_at' => now()->subDays(3)]);

    expect($ticket->isBlockedLate())->toBeTrue()
        ->and($ticket->isLate())->toBeTrue()
        ->and(SupportTicket::queueCounts()['blockedLate'])->toBe(1);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('selectQueue', 'blocked')
        ->assertSee('Que Desarrollo revise el límite de la cola')
        ->assertSee('Desarrollo')
        ->assertSee('Venció el');
});

test('assigning a new report takes it in hand, and a stranger cannot be assigned', function (): void {
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('assignedTo', (string) $this->admin->id);

    expect($ticket->refresh()->assigned_to)->toBe($this->admin->id)
        ->and($ticket->status)->toBe(SupportTicketStatus::Open);

    $client = User::factory()->create();
    $client->assignRole('client');

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('assignedTo', (string) $client->id);

    // Somebody who cannot open the panel cannot be given the report in it.
    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('the priority moves from the panel and sets the clock', function (): void {
    $ticket = workTicket(['created_at' => now()->subHours(10)]);

    expect($ticket->isOverdue())->toBeFalse();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('priority', 'high');

    // 10 hours is nothing at 24 and late at 8.
    expect($ticket->refresh()->priority)->toBe(SupportTicketPriority::High)
        ->and($ticket->isOverdue())->toBeTrue();
});

test('the clock follows what the report is: a problem waits less, a question more, an idea never', function (): void {
    $problem = workTicket(['kind' => SupportTicketKind::Problem, 'priority' => SupportTicketPriority::High, 'created_at' => now()->subHours(9)]);
    $question = workTicket(['kind' => SupportTicketKind::Question, 'priority' => SupportTicketPriority::Normal, 'created_at' => now()->subHours(9)]);
    $idea = workTicket(['kind' => SupportTicketKind::Idea, 'priority' => SupportTicketPriority::High, 'created_at' => now()->subDays(20)]);

    expect($problem->isOverdue())->toBeTrue()
        ->and($question->isOverdue())->toBeFalse()
        // Even a high-priority idea runs no clock: nobody is stuck while it waits.
        ->and($idea->isOverdue())->toBeFalse()
        ->and(SupportTicketPriority::startingFor(SupportTicketKind::Problem))->toBe(SupportTicketPriority::High)
        ->and(SupportTicketPriority::startingFor(SupportTicketKind::Question))->toBe(SupportTicketPriority::Normal)
        ->and(SupportTicketPriority::startingFor(SupportTicketKind::Idea))->toBe(SupportTicketPriority::Low);
});

test('each queue counts its own, and the red dot counts only the late ones', function (): void {
    workTicket(['created_at' => now()->subHours(30)]);
    workTicket(['created_at' => now()->subHour()]);
    workTicket(['status' => SupportTicketStatus::Waiting]);
    workTicket(['status' => SupportTicketStatus::Resolved]);

    expect(SupportTicket::queueCounts())->toMatchArray(['answer' => 2, 'waiting' => 1, 'blocked' => 0, 'resolved' => 1, 'late' => 1]);
});

test('the customer behind a report says whether it pays, whether its WhatsApp works and where an answer lands', function (): void {
    Subscription::query()->where('business_id', $this->business->id)->delete();
    Subscription::factory()->create(['business_id' => $this->business->id, 'plan' => 'negocio', 'status' => SubscriptionStatus::Active]);
    $ticket = workTicket();
    workTicket(['code' => 'ATN-OTROX']);

    $customer = new SupportCustomer($this->business->refresh(), $ticket->id);

    expect($customer->plan)->toBe('Negocio')
        ->and($customer->payment['label'])->toBe('Al día')
        ->and($customer->whatsapp['label'])->toBe('Desconectado')
        ->and($customer->hasAlertNumber)->toBeTrue()
        ->and($customer->others)->toHaveCount(1);

    $this->business->update(['fallback_whatsapp_number' => null]);

    expect((new SupportCustomer($this->business->refresh(), $ticket->id))->hasAlertNumber)->toBeFalse();
});

test('an empty queue is a place with a reason, kept apart from the filters above it', function (): void {
    $empty = Livewire::actingAs($this->admin)->test('admin.support.index');

    $empty->assertSee('No hay reportes')->assertSeeHtml('empty-framed');

    workTicket(['status' => SupportTicketStatus::Resolved]);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->assertSee('Nada por responder')
        ->assertDontSee('No hay reportes');
});

test('the work panel opens from the link of a report and closes without losing the queue', function (): void {
    $ticket = workTicket(['body' => 'No me carga el catálogo']);

    Livewire::withQueryParams(['reporte' => $ticket->id])
        ->actingAs($this->admin)
        ->test('admin.support.index')
        ->assertSet('open', $ticket->id)
        ->assertSee('Conversación')
        ->call('closeTicket')
        ->assertSet('open', null)
        ->assertSee('No me carga el catálogo');
});

test('a resolved report can be reopened, and says how long it took', function (): void {
    $ticket = workTicket(['status' => SupportTicketStatus::Resolved, 'created_at' => now()->subHours(5), 'resolved_at' => now()->subHours(3)]);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->assertSee('Resuelto en 2h')
        ->call('reopen');

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull();
});

test('"my reports" narrows the queue to what is hers and toggles back off', function (): void {
    $mine = workTicket(['assigned_to' => $this->admin->id, 'body' => 'Reporte que ya tomé']);
    $other = User::factory()->create();
    $other->assignRole('admin');
    workTicket(['assigned_to' => $other->id, 'body' => 'Reporte de otra persona']);
    workTicket(['body' => 'Reporte que nadie tiene']);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('toggleMine')
        ->assertSet('who', (string) $this->admin->id)
        ->assertSee('Reporte que ya tomé')
        ->assertDontSee('Reporte de otra persona')
        ->assertDontSee('Reporte que nadie tiene')
        ->call('toggleMine')
        ->assertSet('who', '')
        ->assertSee('Reporte que nadie tiene');

    expect($mine->refresh()->assigned_to)->toBe($this->admin->id);
});

test('a saved reply is pasted into the answer, appended to what she already wrote', function (): void {
    $reply = SupportReply::factory()->create(['name' => 'Ya lo arreglamos', 'body' => 'Ya lo arreglamos, prueba otra vez.']);
    $off = SupportReply::factory()->create(['name' => 'Apagada', 'body' => 'No debería pegarse nunca.', 'is_active' => false]);
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->set('reply.body', 'Hola María,')
        ->set('saved', (string) $reply->id)
        ->assertSet('reply.body', "Hola María,\nYa lo arreglamos, prueba otra vez.")
        // The selector goes back to empty so the same one can be picked again.
        ->assertSet('saved', '')
        ->set('reply.body', '')
        ->set('saved', (string) $off->id)
        ->assertSet('reply.body', '');
});

test('the composer offers no saved reply selector while the shelf is empty', function (): void {
    $ticket = workTicket();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->assertDontSee('Respuesta guardada');

    SupportReply::factory()->create();

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('openTicket', $ticket->id)
        ->assertSee('Respuesta guardada');
});

test('the median resolution is by kind, ignores the old and the unsettled, and says so when empty', function (): void {
    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->assertSee('todavía no hay reportes resueltos para calcularla');

    // Problems: 1h, 3h and 5h settle to a median of 3h, which an average would not give with one outlier.
    foreach ([1, 3, 5] as $hours) {
        workTicket(['kind' => SupportTicketKind::Problem, 'status' => SupportTicketStatus::Resolved, 'created_at' => now()->subDays(2), 'resolved_at' => now()->subDays(2)->addHours($hours)]);
    }
    // An even count averages the two in the middle: 1d and 3d is 2d.
    foreach ([1, 3] as $days) {
        workTicket(['kind' => SupportTicketKind::Question, 'status' => SupportTicketStatus::Resolved, 'created_at' => now()->subDays(10), 'resolved_at' => now()->subDays(10)->addDays($days)]);
    }
    // Neither of these counts: one settled long ago, one not settled at all.
    workTicket(['kind' => SupportTicketKind::Idea, 'status' => SupportTicketStatus::Resolved, 'created_at' => now()->subDays(200), 'resolved_at' => now()->subDays(199)]);
    workTicket(['kind' => SupportTicketKind::Idea]);

    expect(SupportTicket::medianResolution())->toBe(['problem' => '3h', 'question' => '2d']);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->assertSee('Mediana en resolver, últimos 90 días')
        ->assertDontSee('todavía no hay reportes resueltos');
});

test('a client never opens the support panel', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate($this->business)->save();

    $this->actingAs($client->refresh())->get('/admin/soporte')->assertForbidden();
});
