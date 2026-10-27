<?php

declare(strict_types=1);

use App\Classes\Report\ReportLetterhead;
use App\Dto\ReportDto;
use App\Enums\ReportAudience;
use App\Models\Business;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito', 'tagline' => 'Tu negocio, atendido por IA.', 'email' => 'soporte@aliadito.com', 'web' => 'https://www.aliadito.com']);
});

/** An owner whose directory has an Ana and a Luis. */
function reportOwner(array $business = []): User
{
    $owner = User::factory()->create();
    $owner->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida', 'tax_id' => 'J-31245678-9', 'address' => 'Calle 24, N 2-4', 'city' => 'Mérida', ...$business]))->save();

    Customer::factory()->create(['business_id' => $owner->business_id, 'profile_name' => 'Ana Pérez', 'phone' => '04145550100', 'last_activity_at' => now()]);
    Customer::factory()->create(['business_id' => $owner->business_id, 'profile_name' => 'Luis Mora', 'phone' => '04245550101', 'last_activity_at' => now()->subDay()]);

    return $owner;
}

function reportSheet(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $content);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    return [$sheet, fn (string $cell): string => (string) $sheet->getCell($cell)->getValue()];
}

test('the customers file holds this business directory and nobody else\'s', function (): void {
    $owner = reportOwner();
    Customer::factory()->create(['business_id' => Business::factory()->create()->id, 'profile_name' => 'Intrusa Ajena']);

    $csv = (string) $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->assertOk()->getContent();

    expect($csv)->toContain('Ana Pérez')->toContain('Luis Mora')->not->toContain('Intrusa Ajena');
});

test('what is on the screen is what the file holds, and everything when it is asked for', function (): void {
    $owner = reportOwner();

    $seen = (string) $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv', 'buscar' => 'ana']))->getContent();
    $all = (string) $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv', 'buscar' => 'ana', 'alcance' => 'todo']))->getContent();

    expect($seen)->toContain('Ana Pérez')->not->toContain('Luis Mora')
        ->and($all)->toContain('Ana Pérez')->toContain('Luis Mora');
});

test('the file name says whose it is and whether it is a cut of the list', function (): void {
    // The factory picks a random country, whose zone can already be on another day than UTC.
    $owner = reportOwner(['timezone' => 'UTC']);
    $date = now()->format('Y-m-d');

    $cut = $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'xlsx', 'buscar' => 'ana']));
    $whole = $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'xlsx']));
    $everything = $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'xlsx', 'buscar' => 'ana', 'alcance' => 'todo']));

    expect($cut->headers->get('Content-Disposition'))->toContain('Clientes_laboratorio-vida_'.$date.'_filtrado.xlsx')
        ->and($whole->headers->get('Content-Disposition'))->toContain('Clientes_laboratorio-vida_'.$date.'.xlsx')
        ->and($everything->headers->get('Content-Disposition'))->not->toContain('filtrado');
});

test('the excel carries the letterhead, then a table with frozen titles and filters', function (): void {
    $owner = reportOwner();

    $content = (string) $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'xlsx', 'buscar' => 'ana']))->getContent();
    [$sheet, $cell] = reportSheet($content);

    $head = collect(range(1, 20))->first(fn (int $row): bool => $cell('A'.$row) === __('reports.customers.name'));
    $letterhead = collect(range(1, $head - 1))->map(fn (int $row): string => $cell('A'.$row));
    $first = $head + 1;

    expect($cell('A1'))->toContain('Aliadito')->toContain('Tu negocio, atendido por IA.')
        ->and($cell('A2'))->toBe('Clientes')
        ->and($cell('A3'))->toContain('Laboratorio Vida')->not->toContain('J-31245678-9')
        ->and($letterhead->first(fn (string $line): bool => str_contains($line, 'J-31245678-9')))->not->toContain('Laboratorio Vida')
        ->and($letterhead->first(fn (string $line): bool => str_contains($line, 'Mérida')))->not->toBeNull()
        ->and($letterhead->implode("\n"))->not->toContain('@')->toContain('Búsqueda = «ana»')->toContain('1 de 2')
        ->and($cell('A'.$first))->toBe('Ana Pérez')
        ->and($sheet->getCell('B'.$first)->getDataType())->toBe('s')
        ->and($cell('B'.$first))->toBe('04145550100')
        ->and($sheet->getFreezePane())->toBe('A'.$first)
        ->and($sheet->getAutoFilter()->getRange())->toStartWith('A'.$head.':');
});

test('the csv is only data: the titles on the first row and no letterhead', function (): void {
    $csv = (string) $this->actingAs(reportOwner())->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->getContent();
    $first = strtok(ltrim($csv, "\u{FEFF}"), "\n");

    expect($csv)->toStartWith("\u{FEFF}")
        ->and($first)->toStartWith(__('reports.customers.name').';'.__('reports.customers.phone').';'.__('reports.customers.email'))
        ->and($csv)->not->toContain('Aliadito')->not->toContain('Laboratorio Vida');
});

test('the letterhead leaves out what the business never filled', function (): void {
    $owner = reportOwner(['tax_id' => null, 'address' => null, 'city' => null]);
    $report = new ReportDto('Clientes', 'clientes', ['a'], [['x']], audience: ReportAudience::Client);

    $letterhead = ReportLetterhead::for($report, $owner);
    $labels = collect($letterhead->subjectLines)->pluck('label');

    expect($letterhead->subjectName)->toBe('Laboratorio Vida')
        ->and($labels)->not->toContain(__('reports.letterhead.tax_id'))
        ->not->toContain(__('reports.letterhead.address'))
        ->and($letterhead->brand)->toBe('Aliadito')
        ->and($letterhead->tagline)->toBe('Tu negocio, atendido por IA.');
});

test('a platform report is issued by the company and has no client block', function (): void {
    Company::query()->update(['legal_name' => 'Aliadito Firma', 'tax_id' => 'V-12345678-0']);
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $letterhead = ReportLetterhead::for(new ReportDto('Pagos', 'pagos', ['a'], []), $admin);

    expect($letterhead->subjectLabel)->toBe(__('reports.letterhead.issuer'))
        ->and($letterhead->subjectName)->toBe('Aliadito Firma')
        ->and(collect($letterhead->subjectLines)->pluck('value'))->toContain('V-12345678-0');
});

test('a long list prints over several pages and the pdf opens in the browser', function (): void {
    $owner = reportOwner();
    Customer::factory()->count(110)->create(['business_id' => $owner->business_id]);

    $response = $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'pdf']))->assertOk();
    $pdf = (string) $response->getContent();

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBeGreaterThan(2);
});

test('only a client with a business can export the customers', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $this->actingAs($admin)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->assertForbidden();

    auth()->logout();
    $this->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->assertRedirect(route('login'));
});

test('the export menu asks what to export only when a filter is active', function (): void {
    $owner = reportOwner();
    $this->actingAs($owner);

    $filtered = (string) $this->blade('<x-ui.export-menu report="clientes" :params="[\'buscar\' => \'ana\']" />');
    $plain = (string) $this->blade('<x-ui.export-menu report="clientes" :params="[]" />');

    expect($filtered)->toContain(__('reports.menu.scope_title'))
        ->toContain(__('reports.menu.recommended'))
        ->toContain('alcance=todo')
        ->toContain('Búsqueda: «ana»')
        ->and($plain)->not->toContain(__('reports.menu.scope_title'))
        ->toContain(route('reports.show', ['report' => 'clientes', 'format' => 'xlsx']));
});
