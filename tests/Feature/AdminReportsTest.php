<?php

declare(strict_types=1);

use App\Classes\Report\CatalogReport;
use App\Enums\SubscriptionStatus;
use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\Company;
use App\Models\ComplianceEvent;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Models\DemoTag;
use App\Models\ModerationFlag;
use App\Models\Payment;
use App\Models\PlatformContact;
use App\Models\Province;
use App\Models\Region;
use App\Models\SeasonalWindow;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WhatsappVariant;
use Database\Seeders\CatalogFormSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
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

test('every master with a switch exports it as its screen draws it: «Estado» and its own word, never a bare yes or no', function (): void {
    $this->seed(CatalogFormSeeder::class);
    CountryHoliday::query()->create(['country_id' => Country::factory()->create()->id, 'name' => 'Día Qzv', 'month' => 6, 'day' => 17]);
    $checked = 0;

    foreach (CatalogReport::MASTERS as $master => $model) {
        $row = (new $model)->catalogRows()->first() ?? (function () use ($model): ?array {
            $model::factory()->create();

            return (new $model)->catalogRows()->first();
        })();

        if ($row === null || ! array_key_exists('active', $row)) {
            continue;
        }

        $wording = 'catalog.'.str_replace('-', '_', $master);
        $lines = explode("\n", trim(ltrim(platformCsv('catalogo', ['maestro' => $master]), "\u{FEFF}")));
        $column = array_search(__("{$wording}.columns.status"), str_getcsv($lines[0], ';', '"', ''), true);
        $word = str_getcsv($lines[1], ';', '"', '')[$column === false ? 0 : $column] ?? '';

        expect($column)->not->toBeFalse("{$master} exports no «Estado» heading")
            ->and($word)->toBe(__("{$wording}.status.".($row['active'] ? 'active' : 'inactive')), "{$master} exports «{$word}»");
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(19);
});

test('a holiday exports its kind and a plan its tags in the words the screen draws, not a code or a yes or no', function (): void {
    $this->seed(CatalogFormSeeder::class);
    CountryHoliday::query()->create(['country_id' => Country::factory()->create()->id, 'name' => 'Día Qzv', 'month' => 6, 'day' => 17]);
    $this->seed(PlanSeeder::class);
    $featured = SubscriptionPlan::query()->where('is_featured', true)->firstOrFail();
    $featured->update(['trial_days' => 9]);
    $holidays = platformCsv('catalogo', ['maestro' => 'country-holiday']);
    $plans = platformCsv('catalogo', ['maestro' => 'plan']);
    $plan = collect(explode("\n", $plans))->first(fn (string $row): bool => str_starts_with($row, $featured->code.';')) ?? '';

    expect($holidays)->toContain(__('catalog.country_holiday.kinds.fixed'))->not->toContain(';fixed;')
        ->and(strtok(ltrim($plans, "\u{FEFF}"), "\n"))->toContain(__('catalog.plan.columns.tags'))->not->toContain(__('catalog.plan.tags.archived'))
        ->and($plan)->toContain(__('catalog.plan.tags.featured'))->toContain(__('catalog.plan.tags.trial', ['days' => 9]))->not->toContain(';'.__('reports/admin.yes'));
});

test('a season, its WhatsApp and its examples export what the screen says: why one is off, and «Hereda» rather than a blank', function (): void {
    $this->seed(CatalogFormSeeder::class);
    $today = SeasonalWindow::today();
    $off = SeasonalWindow::factory()->create(['name' => 'Navidad Qzv', 'is_active' => false, 'starts_at' => $today->subDay(), 'ends_at' => $today->addDays(5)]);
    WhatsappVariant::factory()->create(['seasonal_window_id' => $off->id, 'phone' => null, 'opening_schedule' => null, 'is_active' => true]);
    DemoTag::factory()->create(['slug' => 'qzv', 'label' => 'Taller Qzv', 'business_name' => null, 'seasonal_window_id' => $off->id]);
    DemoTag::factory()->create(['slug' => 'qzy', 'label' => 'Kiosco Qzy', 'business_name' => 'Kiosco Wvp', 'seasonal_window_id' => null]);
    $line = fn (string $master, string $needle): string => collect(explode("\n", platformCsv('catalogo', ['maestro' => $master])))->first(fn (string $row): bool => str_contains($row, $needle)) ?? '';

    expect($line('seasonal-window', 'Navidad Qzv'))->toContain(__('catalog.seasonal_window.status.off'))->not->toContain(__('catalog.seasonal_window.status.waiting'))
        ->and($line('whatsapp-variant', 'Navidad Qzv'))->toContain(__('catalog.whatsapp_variant.status.season_off'))
        ->and(substr_count($line('whatsapp-variant', 'Navidad Qzv'), __('catalog.whatsapp_variant.status.inherits')))->toBe(2)
        ->and($line('demo-tag', 'Taller Qzv'))->toContain(__('catalog.demo_tag.status.inherits'))
        // A year-round example says so instead of leaving its season blank.
        ->and($line('demo-tag', 'Kiosco Qzy'))->toContain(__('catalog.demo_tag.status.evergreen'));
});

test('a platform report is stamped on the platform clock, not on UTC', function (): void {
    $region = Region::factory()->for(Province::factory()->for(Country::factory()->state(['iso2' => 'VE'])))->create();
    Company::query()->update(['region_id' => $region->id]);
    Once::flush();

    $xlsx = (string) $this->actingAs(platformStaff())->get(route('reports.show', ['report' => 'negocios', 'format' => 'xlsx']))->getContent();
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $xlsx);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    $stamp = collect(range(1, 9))->map(fn (int $row): string => (string) $sheet->getCell('A'.$row)->getValue())
        ->first(fn (string $line): bool => str_contains($line, 'Generado'));

    expect($stamp)->toContain('America/Caracas (UTC-04:00)')->not->toContain('UTC (UTC+00:00)');
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
