<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
});

function knowledgeDigestBusiness(): Business
{
    return Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '+54 9 299 552-9100',
    ]);
}

test('the owner receives the weekly learning recap on the fallback number', function (): void {
    Queue::fake();

    $business = knowledgeDigestBusiness();

    KnowledgeDocument::factory()->count(2)->create([
        'business_id' => $business->id,
        'source_type' => 'faq',
    ]);

    // Three asks of two distinct questions: shipping is the most asked.
    $thread = Conversation::factory()->create(['business_id' => $business->id]);
    KnowledgeSuggestion::factory()->askedIn($thread)->askedIn($thread)->create(['business_id' => $business->id, 'question' => '¿Hacen envíos?']);
    KnowledgeSuggestion::factory()->askedIn($thread)->create(['business_id' => $business->id, 'question' => '¿Aceptan Visa?']);

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/message/sendText/atendia-demo')
            && $request['number'] === '5492995529100'
            && str_contains((string) $request['text'], 'Laboratorio Vida')
            && str_contains((string) $request['text'], '2 respuestas nuevas')
            && str_contains((string) $request['text'], '2 preguntas quedaron sin respuesta')
            && str_contains((string) $request['text'], '«¿Hacen envíos?»')
            && str_contains((string) $request['text'], route('assistant'));
    });
});

test('a week with nothing learned and nothing missed sends no recap', function (): void {
    Queue::fake();

    $business = knowledgeDigestBusiness();

    // Old activity only: outside the seven-day window.
    KnowledgeDocument::factory()->create([
        'business_id' => $business->id,
        'source_type' => 'faq',
        'created_at' => now()->subDays(10),
    ]);
    $this->travel(-10)->days();
    KnowledgeSuggestion::factory()->askedIn(Conversation::factory()->create(['business_id' => $business->id]))->create(['business_id' => $business->id]);
    $this->travelBack();

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Http::assertNothingSent();
});

test('a business without a connected WhatsApp is skipped', function (): void {
    Queue::fake();

    $business = Business::factory()->create(['whatsapp_instance' => null]);
    KnowledgeSuggestion::factory()->askedIn(Conversation::factory()->create(['business_id' => $business->id]))->create(['business_id' => $business->id]);

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Http::assertNothingSent();
});

test('the teach footer only rides along when something went unanswered', function (): void {
    Queue::fake();

    $business = knowledgeDigestBusiness();
    KnowledgeDocument::factory()->create([
        'business_id' => $business->id,
        'source_type' => 'faq',
    ]);

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], '1 respuesta nueva')
        && ! str_contains((string) $request['text'], route('assistant')));
});
