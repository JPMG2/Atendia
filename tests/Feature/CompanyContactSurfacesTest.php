<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Mail and error pages wear the company row, like the footer
|--------------------------------------------------------------------------
| Both layouts printed only the brand; the address, the mailbox and the
| networks the Compañía screen stores were captured and never shown there.
*/

function companyWithNetwork(): Company
{
    $company = Company::factory()->create([
        'legal_name' => 'AtendIa SRL',
        'address' => 'Av. Siempre Viva 742',
        'email' => 'hola@atendia.test',
    ]);

    SocialLink::factory()->create([
        'linkable_type' => $company->getMorphClass(),
        'linkable_id' => $company->getKey(),
        'social_network_id' => SocialNetwork::factory()->create(['name' => 'Instagram', 'icon' => 'link'])->getKey(),
        'url' => 'https://instagram.com/atendia',
    ]);

    return $company;
}

test('the mail layout prints the address, the mailbox and the networks by name', function (): void {
    companyWithNetwork();

    $html = Blade::render('<x-email.layout>cuerpo</x-email.layout>');

    expect($html)
        ->toContain('Av. Siempre Viva 742')
        ->toContain('mailto:hola@atendia.test')
        ->toContain('https://instagram.com/atendia')
        ->toContain('Instagram');
});

test('the error pages print the same contact lines', function (): void {
    companyWithNetwork();

    $html = view('errors.404')->render();

    expect($html)
        ->toContain('AtendIa SRL')
        ->toContain('Av. Siempre Viva 742')
        ->toContain('mailto:hola@atendia.test')
        ->toContain('https://instagram.com/atendia');
});

test('with no company row both surfaces render with no contact lines', function (): void {
    expect(Blade::render('<x-email.layout>cuerpo</x-email.layout>'))
        ->toContain('cuerpo')
        ->not->toContain('mailto:');

    expect(view('errors.500')->render())->not->toContain('mailto:');
});

test('contact never throws when the database cannot be read', function (): void {
    // Postgres DDL is transactional, so the rollback puts the table back.
    Schema::drop('companies');

    $contact = Company::contact();

    expect($contact['email'])->toBeNull()
        ->and($contact['links'])->toBeEmpty()
        ->and($contact['legal_name'])->not->toBe('');
});
