<?php

declare(strict_types=1);

use App\Classes\Report\CatalogReport;
use App\Enums\SubscriptionStatus;
use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\Company;
use App\Models\ComplianceEvent;
use App\Models\ModerationFlag;
use App\Models\Payment;
use App\Models\PlatformContact;
use App\Models\Subscription;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);
});

function platformStaff(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->syncRoles(['admin']));
}

function platformCsv(string $report, array $query = []): string
{
    return (string) test()->actingAs(platformStaff())
        ->get(route('reports.show', ['report' => $report, 'format' => 'csv', ...$query]))
        ->assertOk()->getContent();
}

dataset('admin reports', [
    'negocios', 'adopcion', 'incidencias', 'radar', 'cumplimiento',
    'cobranza', 'cobros', 'calidad-ia', 'moderacion', 'testimonios',
    'soporte', 'usuarios', 'auditoria', 'avisos', 'menciones', 'modelos-ia',
]);

test('a client cannot pull an admin report', function (string $report): void {
    $client = User::factory()->create();
    $client->syncRoles(['client']);

    $this->actingAs($client)->get(route('reports.show', ['report' => $report, 'format' => 'csv']))->assertForbidden();
})->with('admin reports');

test('every admin report renders on an empty platform in every format', function (string $report): void {
    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->actingAs(platformStaff())
            ->get(route('reports.show', ['report' => $report, 'format' => $format]))
            ->assertOk();
    }
})->with('admin reports');

test('businesses are cut by the search the screen uses, and all of them when asked', function (): void {
    Business::factory()->create(['name' => 'Panadería Sol']);
    Business::factory()->create(['name' => 'Taller Luna']);

    $search = platformCsv('negocios', ['buscar' => 'luna']);
    $everything = platformCsv('negocios', ['buscar' => 'luna', 'alcance' => 'todo']);

    expect($search)->toContain('Taller Luna')->not->toContain('Panadería Sol')
        ->and($everything)->toContain('Taller Luna')->toContain('Panadería Sol');
});

test('ledgers carry their rows: payments, moderation, testimonials, compliance, collections and ratings', function (): void {
    $business = Business::factory()->create(['name' => 'Óptica Norte']);
    $subscription = Subscription::factory()->create(['business_id' => $business->id, 'status' => SubscriptionStatus::PastDue]);
    Payment::factory()->paid()->create(['business_id' => $business->id, 'reference' => 'REF-778899']);
    ModerationFlag::factory()->create(['business_id' => $business->id, 'category' => 'violence-test']);
    Testimonial::factory()->create(['business_id' => $business->id, 'quote' => 'Nos cambió el mes']);
    ComplianceEvent::factory()->create(['business_id' => $business->id]);
    AssistantRating::factory()->create(['business_id' => $business->id]);

    expect($subscription->exists)->toBeTrue()
        ->and(platformCsv('cobros'))->toContain('Óptica Norte')->toContain('REF-778899')
        ->and(platformCsv('moderacion'))->toContain('violence-test')
        ->and(platformCsv('testimonios'))->toContain('Nos cambió el mes')
        ->and(platformCsv('cumplimiento'))->toContain('Óptica Norte')
        ->and(platformCsv('cobranza'))->toContain('Óptica Norte')
        ->and(platformCsv('calidad-ia'))->toContain('Óptica Norte')
        ->and(platformCsv('negocios'))->toContain('Óptica Norte');
});

test('every master of the catalog hub exports with real headings and obeys its own permission', function (): void {
    $this->seed(CatalogFormSeeder::class);

    foreach (array_keys(CatalogReport::MASTERS) as $master) {
        $csv = platformCsv('catalogo', ['maestro' => $master]);
        $heading = strtok($csv, "\n");

        expect($heading)->not->toBeEmpty("{$master} has no heading row");
    }

    $this->actingAs(platformStaff())->get(route('reports.show', ['report' => 'catalogo', 'format' => 'csv']))->assertForbidden();

    $client = User::factory()->create();
    $client->syncRoles(['client']);
    $this->actingAs($client)->get(route('reports.show', ['report' => 'catalogo', 'format' => 'csv', 'maestro' => 'currency']))->assertForbidden();
});

test('the issuer block lists one datum per row and leaves the email out', function (): void {
    Company::query()->update(['legal_name' => 'Yuca SA', 'tax_id' => '12352659', 'address' => 'Calle 24 - 2 - 4', 'phone' => '5492995529100', 'email' => 'soporte@aliadito.com']);

    $xlsx = (string) $this->actingAs(platformStaff())->get(route('reports.show', ['report' => 'negocios', 'format' => 'xlsx']))->getContent();
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $xlsx);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    $lines = collect(range(1, 9))->map(fn (int $row): string => (string) $sheet->getCell('A'.$row)->getValue());

    expect($lines->implode("\n"))->toContain('Yuca SA')->not->toContain('@')
        ->and($lines->first(fn (string $line): bool => str_contains($line, '12352659')))->not->toContain('Yuca SA')->not->toContain('Calle 24')
        ->and($lines->first(fn (string $line): bool => str_contains($line, 'Calle 24')))->not->toContain('5492995529100');
});

test('the radar lists every contact with the businesses they reach', function (): void {
    PlatformContact::query()->create(['phone' => '584141234567', 'name' => 'Rosa Márquez', 'first_seen_at' => now(), 'last_activity_at' => now()]);

    expect(platformCsv('radar'))->toContain('584141234567')->toContain('Rosa Márquez');
});
