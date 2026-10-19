<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
});

function reportClient(): User
{
    $owner = User::factory()->create(['name' => 'Dueña Prueba', 'email' => 'duena@prueba.com']);
    $owner->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida']))->save();

    return $owner;
}

function reportCsv(User $user, string $report, array $query = []): string
{
    return (string) test()->actingAs($user)->get(route('reports.show', ['report' => $report, 'format' => 'csv', ...$query]))->assertOk()->getContent();
}

test('conversations list the threads, cut by the search and the dates the screen uses', function (): void {
    $owner = reportClient();
    $ana = Conversation::factory()->create(['business_id' => $owner->business_id, 'contact_name' => 'Ana Pérez', 'last_message_at' => now()]);
    $old = Conversation::factory()->create(['business_id' => $owner->business_id, 'contact_name' => 'Luis Mora', 'last_message_at' => now()->subYears(2)]);
    ConversationMessage::factory()->create(['business_id' => $owner->business_id, 'conversation_id' => $ana->id, 'body' => 'Quiero el precio del examen']);

    $all = reportCsv($owner, 'conversaciones');
    $search = reportCsv($owner, 'conversaciones', ['buscar' => 'luis']);
    $dates = reportCsv($owner, 'conversaciones', ['fechas' => now()->subDay()->format('Y-m-d').'..'.now()->addDay()->format('Y-m-d')]);
    $everything = reportCsv($owner, 'conversaciones', ['fechas' => '2000-01-01..2000-01-02', 'alcance' => 'todo']);

    expect($all)->toContain('Ana Pérez')->toContain('Luis Mora')->toContain('Quiero el precio del examen')
        ->and($search)->toContain('Luis Mora')->not->toContain('Ana Pérez')
        ->and($dates)->toContain('Ana Pérez')->not->toContain('Luis Mora')
        ->and($everything)->toContain('Ana Pérez')->toContain('Luis Mora');
    expect($old->id)->not->toBe($ana->id);
});

test('the agenda exports the day or week on screen, and the whole history when asked', function (): void {
    $owner = reportClient();
    $customer = Customer::factory()->create(['business_id' => $owner->business_id, 'profile_name' => 'Rosa Márquez']);
    $service = Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ecografía', 'duration_minutes' => 30]);
    $today = now()->setTime(10, 0);

    Appointment::factory()->create(['business_id' => $owner->business_id, 'customer_id' => $customer->id, 'service_id' => $service->id, 'starts_at' => $today, 'ends_at' => $today->copy()->addMinutes(30)]);
    Appointment::factory()->create(['business_id' => $owner->business_id, 'customer_id' => Customer::factory()->create(['business_id' => $owner->business_id, 'profile_name' => 'Pedro Antiguo'])->id, 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonths(2)->addMinutes(30), 'status' => AppointmentStatus::Done]);

    $day = reportCsv($owner, 'agenda', ['dia' => $today->format('Y-m-d'), 'vista' => 'day']);
    $everything = reportCsv($owner, 'agenda', ['dia' => $today->format('Y-m-d'), 'vista' => 'day', 'alcance' => 'todo']);

    expect($day)->toContain('Rosa Márquez')->toContain('Ecografía')->not->toContain('Pedro Antiguo')
        ->and($everything)->toContain('Rosa Márquez')->toContain('Pedro Antiguo');
});

test('services and products follow the search and state filters of their screens', function (): void {
    $owner = reportClient();
    Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ecografía', 'is_active' => true, 'price' => 40]);
    Service::factory()->create(['business_id' => $owner->business_id, 'name' => 'Rayos X', 'is_active' => false]);
    Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Guantes', 'in_stock' => true]);
    Product::factory()->create(['business_id' => $owner->business_id, 'name' => 'Jeringas', 'in_stock' => false]);

    $paused = reportCsv($owner, 'servicios', ['filtro' => 'paused']);
    $found = reportCsv($owner, 'servicios', ['buscar' => 'ecograf']);
    $out = reportCsv($owner, 'productos', ['filtro' => 'out']);
    $all = reportCsv($owner, 'productos', ['filtro' => 'out', 'alcance' => 'todo']);

    expect($paused)->toContain('Rayos X')->not->toContain('Ecografía')
        ->and($found)->toContain('Ecografía')->not->toContain('Rayos X')
        ->and($out)->toContain('Jeringas')->not->toContain('Guantes')
        ->and($all)->toContain('Jeringas')->toContain('Guantes');
});

test('the team report lists the people with access to this business only', function (): void {
    $owner = reportClient();
    User::factory()->create(['name' => 'Persona Ajena', 'business_id' => Business::factory()->create()->id]);

    $csv = reportCsv($owner, 'equipo');

    expect($csv)->toContain('Dueña Prueba')->toContain('duena@prueba.com')->not->toContain('Persona Ajena');
});

test('my payments export the history of the year on screen', function (): void {
    $owner = reportClient();
    Payment::factory()->create(['business_id' => $owner->business_id, 'reference' => 'REF-2026', 'created_at' => now()->setYear(2026)]);
    Payment::factory()->create(['business_id' => $owner->business_id, 'reference' => 'REF-2024', 'created_at' => now()->setYear(2024)]);

    $year = reportCsv($owner, 'pagos', ['anio' => '2026']);
    $all = reportCsv($owner, 'pagos');

    expect($year)->toContain('REF-2026')->not->toContain('REF-2024')
        ->and($all)->toContain('REF-2026')->toContain('REF-2024');
});

test('every client report is locked to a client with a business and never reaches another one', function (string $report): void {
    $owner = reportClient();
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get(route('reports.show', ['report' => $report, 'format' => 'csv']))->assertForbidden();
    $this->actingAs($owner)->get(route('reports.show', ['report' => $report, 'format' => 'xlsx']))->assertOk();
    $this->actingAs($owner)->get(route('reports.show', ['report' => $report, 'format' => 'pdf']))->assertOk();
})->with(['conversaciones', 'agenda', 'servicios', 'productos', 'equipo', 'pagos']);

test('the export menu sits on every client list screen', function (string $component): void {
    $owner = reportClient();
    Customer::factory()->create(['business_id' => $owner->business_id]);
    Conversation::factory()->create(['business_id' => $owner->business_id]);
    Service::factory()->create(['business_id' => $owner->business_id]);
    Product::factory()->create(['business_id' => $owner->business_id]);
    Payment::factory()->create(['business_id' => $owner->business_id]);
    $owner->business->update(['appointments_enabled' => true]);

    $this->actingAs($owner);

    Livewire\Livewire::test($component)->assertSeeHtml('export-menu');
})->with(['conversations.index', 'agenda.index', 'goods.index', 'products.index', 'team.index', 'payments.index']);
