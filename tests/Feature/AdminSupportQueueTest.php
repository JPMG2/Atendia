<?php

declare(strict_types=1);

use App\Classes\Main\TicketEvidence;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The support queue is the screen
|--------------------------------------------------------------------------
| A queue is answered by who has waited longest, so the screen has to say how
| long each one has waited, paint the ones past her limit, narrow by what she
| is looking for without reordering, and show the evidence as a person reads it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.support.overdue_hours', 24);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->clinic = Business::factory()->create(['name' => 'Clínica Vida']);
    $this->salon = Business::factory()->create(['name' => 'Peluquería Lola']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->admin = $admin->refresh();
});

function queueTicket(Business $business, array $attributes = []): SupportTicket
{
    return SupportTicket::factory()->for($business)->create($attributes);
}

test('each queue view stands for the statuses it names, and none changes the order', function (): void {
    $new = queueTicket(test()->clinic, ['created_at' => now()->subDays(3)]);
    $open = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Open, 'created_at' => now()->subDays(2)]);
    $waiting = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Waiting, 'created_at' => now()->subDay()]);
    $done = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Resolved, 'created_at' => now()->subDays(5)]);

    expect(SupportTicket::inbox('open')->pluck('id')->all())->toBe([$new->id, $open->id, $waiting->id])
        ->and(SupportTicket::inbox('answer')->pluck('id')->all())->toBe([$new->id, $open->id])
        ->and(SupportTicket::inbox('waiting')->pluck('id')->all())->toBe([$waiting->id])
        ->and(SupportTicket::inbox('resolved')->pluck('id')->all())->toBe([$done->id])
        ->and(SupportTicket::inbox('all'))->toHaveCount(4);
});

test('the queue narrows by kind, by business and by what she types', function (): void {
    $problem = queueTicket(test()->clinic, ['body' => 'No carga el catálogo de servicios', 'code' => 'ATN-AAAAA']);
    $idea = queueTicket(test()->salon, ['kind' => SupportTicketKind::Idea, 'body' => 'Quisiera ver un calendario', 'code' => 'ATN-BBBBB']);

    expect(SupportTicket::inbox('all', kind: 'idea')->pluck('id')->all())->toBe([$idea->id])
        ->and(SupportTicket::inbox('all', businessId: $test = test()->clinic->id)->pluck('id')->all())->toBe([$problem->id])
        // Accents and case are folded: "catalogo" finds "catálogo".
        ->and(SupportTicket::inbox('all', search: 'CATALOGO')->pluck('id')->all())->toBe([$problem->id])
        ->and(SupportTicket::inbox('all', search: 'atn-bbbbb')->pluck('id')->all())->toBe([$idea->id])
        // The business name is searchable too: she remembers WHO, not what.
        ->and(SupportTicket::inbox('all', search: 'peluqueria')->pluck('id')->all())->toBe([$idea->id])
        // A kind that does not exist narrows nothing instead of hiding everything.
        ->and(SupportTicket::inbox('all', kind: 'inventado'))->toHaveCount(2);
});

test('only the businesses that reported something are offered as a filter', function (): void {
    queueTicket(test()->clinic);

    expect(SupportTicket::reporters())->toBe([test()->clinic->id => 'Clínica Vida']);
});

test('a report waits on us while new or open, on the business once we asked, and on nobody once settled', function (): void {
    $new = queueTicket(test()->clinic, ['created_at' => now()->subHours(30)]);
    $waiting = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Waiting, 'created_at' => now()->subDays(9), 'answered_at' => now()->subHours(2)]);
    $done = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Resolved, 'created_at' => now()->subHours(5), 'resolved_at' => now()->subHours(3)]);

    expect($new->waitingSince()->diffInHours(now()))->toEqualWithDelta(30, 0.01)
        // Since our answer, not since the report: the clock is hers now.
        ->and($waiting->waitingSince()->diffInHours(now()))->toEqualWithDelta(2, 0.01)
        ->and($done->waitingSince())->toBeNull()
        ->and($done->resolvedInLabel())->toBe('2h');
});

test('past her limit a report is overdue, but only while the ball is in our court', function (): void {
    $late = queueTicket(test()->clinic, ['created_at' => now()->subHours(25)]);
    $fresh = queueTicket(test()->clinic, ['created_at' => now()->subHours(23)]);
    $ofTheBusiness = queueTicket(test()->clinic, ['status' => SupportTicketStatus::Waiting, 'created_at' => now()->subDays(20), 'answered_at' => now()->subDays(20)]);

    expect($late->isOverdue())->toBeTrue()
        ->and($fresh->isOverdue())->toBeFalse()
        // Waiting on the business is not our delay, however old it is.
        ->and($ofTheBusiness->isOverdue())->toBeFalse();

    config()->set('atendia.support.overdue_hours', 12);

    expect($fresh->refresh()->isOverdue())->toBeTrue();
});

test('the wait is written in its two largest units', function (): void {
    $ticket = queueTicket(test()->clinic, ['created_at' => now()->subDays(3)->subHours(4)->subMinutes(10)]);

    expect($ticket->waitLabel())->toBe('3d 4h');
});

test('the oldest unanswered is the first call, and a settled or waiting one is never it', function (): void {
    queueTicket(test()->clinic, ['status' => SupportTicketStatus::Resolved, 'created_at' => now()->subDays(30)]);
    queueTicket(test()->clinic, ['status' => SupportTicketStatus::Waiting, 'created_at' => now()->subDays(20)]);
    $oldest = queueTicket(test()->clinic, ['created_at' => now()->subDays(2)]);
    queueTicket(test()->clinic, ['created_at' => now()->subHour()]);

    expect(SupportTicket::oldestUnanswered()->id)->toBe($oldest->id);
});

test('the captured context is read as words, not as a dump', function (): void {
    $evidence = new TicketEvidence([
        'url' => 'https://atendia.test/productos',
        'viewport' => '390x844',
        'agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'plan' => 'negocio',
        'locale' => 'es',
        'errors' => 'TypeError: x is undefined | 500 en /livewire/update',
    ]);

    expect($evidence->browser)->toBe('Safari 17 · iOS')
        ->and($evidence->window)->toBe('390×844 · teléfono')
        ->and(collect($evidence->rows)->pluck('value')->all())->toContain('Negocio', 'es', '390×844 · teléfono')
        ->and($evidence->errors)->toBe(['TypeError: x is undefined', '500 en /livewire/update']);
});

test('the browser is told apart even when it pretends to be another', function (): void {
    $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    expect((new TicketEvidence(['agent' => $chrome]))->browser)->toBe('Chrome 141 · Windows')
        ->and((new TicketEvidence(['agent' => $chrome.' Edg/141.0.0.0']))->browser)->toBe('Edge 141 · Windows');
});

test('an old report with no context has no evidence instead of an empty box', function (): void {
    expect((new TicketEvidence(null))->isEmpty)->toBeTrue()
        ->and((new TicketEvidence([]))->isEmpty)->toBeTrue();
});

test('the queue comes first and the late ones are painted', function (): void {
    queueTicket(test()->clinic, ['body' => 'Atrasado de verdad', 'created_at' => now()->subHours(40)]);
    queueTicket(test()->salon, ['body' => 'Recién llegado', 'created_at' => now()->subMinutes(5)]);

    $html = Livewire::actingAs($this->admin)->test('admin.support.index')->html();

    expect(substr_count($html, 'is-over'))->toBe(1)
        // The queue pane is declared before the help pane: the first thing read is who is waiting.
        ->and(strpos($html, 'pane-queue'))->toBeLessThan(strpos($html, 'pane-help'));
});

test('the header names how long the longest wait has lasted', function (): void {
    queueTicket(test()->clinic, ['created_at' => now()->subDays(2)->subHours(3)]);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->assertSee('La más vieja sin responder espera 2d 3h');
});

test('a filter that matches nothing says so instead of claiming there are no reports', function (): void {
    queueTicket(test()->clinic);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->set('search', 'zzzzz')
        ->assertSee('Ningún reporte coincide')
        ->assertDontSee('No hay reportes');
});

test('the filters live in the URL, so a link lands on the same queue', function (): void {
    queueTicket(test()->clinic);
    queueTicket(test()->salon, ['kind' => SupportTicketKind::Idea, 'body' => 'Una idea del salón']);

    Livewire::withQueryParams(['tipo' => 'idea', 'estado' => 'all'])
        ->actingAs($this->admin)
        ->test('admin.support.index')
        ->assertSet('kind', 'idea')
        ->assertSee('Una idea del salón');
});

test('an open report shows its evidence in words and the errors the browser logged', function (): void {
    $ticket = queueTicket(test()->clinic, ['context' => [
        'url' => 'https://atendia.test/productos',
        'viewport' => '1280x900',
        'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'errors' => 'TypeError: x is undefined',
    ]]);

    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->call('toggle', $ticket->id)
        ->assertSee('Chrome 141 · Windows')
        ->assertSee('1280×900 · escritorio')
        ->assertSee('El navegador registró 1 error')
        ->assertSee('TypeError: x is undefined')
        // The machine's JSON is gone.
        ->assertDontSee('"agent"', escape: false);
});

test('the help analysis lives apart, with a badge when something needs her eyes', function (): void {
    Livewire::actingAs($this->admin)->test('admin.support.index')
        ->assertSee('La ayuda');
});
