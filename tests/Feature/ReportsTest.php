<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function reportAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    return $admin;
}

test('imprimir opens a real pdf in the browser', function (): void {
    Company::factory()->create(['legal_name' => 'Atendia SRL']);

    $response = $this->actingAs(reportAdmin())->get(route('reports.show', ['report' => 'company', 'format' => 'pdf']));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline; filename="compania-')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('excel and csv download, csv readable by a Spanish-locale Excel', function (): void {
    Company::factory()->create(['legal_name' => 'Atendia SRL']);
    $admin = reportAdmin();

    $xlsx = $this->actingAs($admin)->get(route('reports.show', ['report' => 'company', 'format' => 'xlsx']));
    $csv = $this->actingAs($admin)->get(route('reports.show', ['report' => 'company', 'format' => 'csv']));

    expect($xlsx->headers->get('Content-Disposition'))->toStartWith('attachment; filename="compania-')
        ->and(substr((string) $xlsx->getContent(), 0, 2))->toBe('PK')
        ->and((string) $csv->getContent())->toStartWith("\u{FEFF}")
        ->toContain('"'.__('company.fields.legal_name').'";"Atendia SRL"');
});

test('the report holds the lock, and unknown reports or formats are not found', function (): void {
    $client = User::factory()->create();
    $client->syncRoles(['client']);

    $this->actingAs($client)->get(route('reports.show', ['report' => 'company', 'format' => 'pdf']))->assertForbidden();
    $this->actingAs(reportAdmin())->get(route('reports.show', ['report' => 'nope', 'format' => 'pdf']))->assertNotFound();
    $this->actingAs(reportAdmin())->get(route('reports.show', ['report' => 'company', 'format' => 'docx']))->assertNotFound();
});

test('a guest is sent to log in', function (): void {
    $this->get(route('reports.show', ['report' => 'company', 'format' => 'pdf']))->assertRedirect(route('login'));
});

test('the export button points at the report in its format, and prints in a new tab', function (): void {
    $html = (string) $this->blade('<x-ui.export-button report="company" format="pdf" /><x-ui.export-button report="company" format="xlsx" />');

    expect($html)->toContain(route('reports.show', ['report' => 'company', 'format' => 'pdf']))
        ->toContain('target="_blank"')
        ->toContain('btn-export')
        ->toContain(__('reports.buttons.xlsx'));
});

test('the export group renders the three formats by default', function (): void {
    $html = (string) $this->blade('<x-ui.export-buttons report="company" />');

    expect($html)->toContain('export-group')
        ->toContain(route('reports.show', ['report' => 'company', 'format' => 'pdf']))
        ->toContain(route('reports.show', ['report' => 'company', 'format' => 'xlsx']))
        ->toContain(route('reports.show', ['report' => 'company', 'format' => 'csv']));
});
