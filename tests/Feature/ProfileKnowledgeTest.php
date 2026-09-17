<?php

declare(strict_types=1);

use App\Actions\Business\SaveBusinessConnection;
use App\Actions\Business\SaveBusinessSchedule;
use App\Actions\Business\SyncProfileKnowledge;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The knowledge observer queues the embedding job: faked, or the suite
    // would ride the network.
    Queue::fake();
});

test('the business card reaches the knowledge base with hours, address and contact', function (): void {
    $business = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'description' => 'Análisis clínicos con turnos.',
        'address' => 'Av. Siempre Viva 123',
        'city' => 'Neuquén',
        'whatsapp_number' => '+54 9 299 5243890',
        'email' => 'hola@vida.test',
    ]);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00']);
    $business->hours()->create(['day_of_week' => 1, 'opens_at' => '17:00', 'closes_at' => '20:00']);

    app(SyncProfileKnowledge::class)->handle($business);

    $document = KnowledgeDocument::query()->where('source_type', 'profile')->sole();

    expect($document->business_id)->toBe($business->id)
        ->and($document->content)
        ->toContain('Negocio: Laboratorio Vida')
        ->toContain('Análisis clínicos con turnos.')
        ->toContain('Dirección: Av. Siempre Viva 123, Neuquén')
        ->toContain('Lunes: 09:00 a 13:00 y 17:00 a 20:00')
        ->toContain('WhatsApp: +54 9 299 5243890')
        ->toContain('Correo: hola@vida.test');
});

test('saving a profile slice republishes the card', function (): void {
    $business = Business::factory()->create();

    app(SaveBusinessConnection::class)->handle($business, ['email' => 'nuevo@negocio.test']);

    expect(KnowledgeDocument::query()->where('source_type', 'profile')->sole()->content)
        ->toContain('Correo: nuevo@negocio.test');

    app(SaveBusinessSchedule::class)->handle($business, [
        5 => [['opens_at' => '08:00', 'closes_at' => '12:00']],
    ]);

    expect(KnowledgeDocument::query()->where('source_type', 'profile')->sole()->content)
        ->toContain('Viernes: 08:00 a 12:00');
});
