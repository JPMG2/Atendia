<?php

declare(strict_types=1);

use App\Classes\Main\MetaDisplayName;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a plain, well written name breaks no rule', function (string $name): void {
    expect((new MetaDisplayName($name))->problems)->toBe([]);
})->with(['Panadería La Espiga', 'Lab Vida', 'Óptica Díaz', 'IBM', 'Kinesio Sur']);

test('each rule of Meta is named when the name breaks it', function (string $name, string $rule): void {
    expect((new MetaDisplayName($name))->problems)->toContain($rule);
})->with([
    'too short' => ['Ab', 'too_short'],
    'shouting' => ['PANADERIA LA ESPIGA', 'all_caps'],
    'emoji' => ['Panadería 🍞', 'emoji'],
    'a web address' => ['laespiga.com', 'url'],
    'a link' => ['https://laespiga.shop', 'url'],
    'the reserved word' => ['Mi WhatsApp Shop', 'whatsapp'],
]);

test('capitals are fine for an acronym but not for a whole name', function (): void {
    expect((new MetaDisplayName('ACME'))->isAcceptable)->toBeTrue()
        ->and((new MetaDisplayName('ACME SERVICIOS DEL SUR'))->isAcceptable)->toBeFalse();
});

test('a business with a risky name still sees the connect button, only warned', function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => 'close']])]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create(['name' => 'PANADERIA 🍞 WHATSAPP']);
    $owner = User::factory()->create(['business_id' => $business->id, 'email_verified_at' => now()]);
    $owner->assignRole('client');

    Livewire::actingAs($owner)->test('whatsapp.link')
        ->assertSee('El nombre de tu negocio puede ser rechazado por Meta')
        ->assertSee('Lleva emojis')
        ->assertSee('Conectar mi WhatsApp');
});

test('a clean name shows no warning', function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    Http::fake(['http://evolution.test/*' => Http::response(['instance' => ['state' => 'close']])]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create(['name' => 'Panadería La Espiga']);
    $owner = User::factory()->create(['business_id' => $business->id, 'email_verified_at' => now()]);
    $owner->assignRole('client');

    Livewire::actingAs($owner)->test('whatsapp.link')
        ->assertDontSee('puede ser rechazado por Meta')
        ->assertSee('Conectar mi WhatsApp');
});
