<?php

declare(strict_types=1);

use App\Enums\SupportTicketPriority;
use App\Models\CatalogForm;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support report kinds, a master and not an Enum
|--------------------------------------------------------------------------
| Nothing in the code branches on WHICH kind a report is: the name, the
| glyph, where it starts and whether it runs a clock are data she edits.
| The column on `support_tickets` keeps the code, so no ticket is rewritten.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogFormSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

test('the three kinds the widget shipped with are seeded, in order, and re-seeding keeps her edits', function (): void {
    expect(SupportTicketKind::offered()->pluck('code')->all())->toBe(['problem', 'idea', 'question']);

    SupportTicketKind::byCode('idea')->update(['name' => 'Sugerencia']);
    $this->seed(SupportTicketKindSeeder::class);

    expect(SupportTicketKind::byCode('idea')->name)->toBe('Sugerencia');
});

test('the master is a row of the hub', function (): void {
    expect(CatalogForm::query()->where('component', 'catalog.support-ticket-kind')->exists())->toBeTrue();
});

test('a kind is created from the hub with its own starting priority and clock', function (): void {
    Livewire::test('catalog.support-ticket-kind')
        ->call('openCreate')
        ->set('form.data.code', 'Facturación')
        ->set('form.data.name', 'Una consulta de facturación')
        ->set('form.data.icon', 'credit-card')
        ->set('form.data.starting_priority', 'high')
        ->set('form.data.runs_clock', true)
        ->set('form.data.sort_order', 40)
        ->call('create')
        ->assertHasNoErrors();

    $kind = SupportTicketKind::byCode('facturacion');

    expect($kind)->not->toBeNull()
        ->and($kind->name)->toBe('Una consulta de facturación')
        ->and($kind->starting_priority)->toBe(SupportTicketPriority::High);
});

test('a code that exists, or an icon that is not in the registry, is refused', function (): void {
    Livewire::test('catalog.support-ticket-kind')
        ->call('openCreate')
        ->set('form.data.code', 'idea')
        ->set('form.data.name', 'Otra idea')
        ->set('form.data.icon', 'no-such-glyph')
        ->call('create')
        ->assertHasErrors(['code', 'icon']);

    expect(SupportTicketKind::query()->count())->toBe(3);
});

test('editing a kind renames it but never rewrites its code', function (): void {
    $idea = SupportTicketKind::byCode('idea');

    Livewire::test('catalog.support-ticket-kind')
        ->call('openEdit', $idea->id)
        ->set('form.data.code', 'sugerencia')
        ->set('form.data.name', 'Sugerencia')
        ->call('update')
        ->assertHasNoErrors();

    $idea->refresh();

    expect($idea->code)->toBe('idea')->and($idea->name)->toBe('Sugerencia');
});

test('a switched-off kind is not offered, but a ticket filed under it still reads its name', function (): void {
    $ticket = SupportTicket::factory()->create(['kind' => 'idea']);

    SupportTicketKind::byCode('idea')->update(['is_active' => false]);

    expect(SupportTicketKind::offered()->pluck('code')->all())->toBe(['problem', 'question'])
        ->and($ticket->fresh()->kind->name)->toBe('Una idea');
});
