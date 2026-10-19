<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Mail\BusinessKnowledgeDigest;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->next('Monday')->setTime(9, 35));
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    Mail::fake();
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
});

function knowledgeDigestBusiness(): Business
{
    $business = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '+54 9 299 552-9100',
        // A fixed clock: the recap leaves Monday at LOCAL 09:30.
        'timezone' => 'UTC',
    ]);

    // The digest is sold from the middle plan up: on the entry plan the
    // command skips the business, which is the lock these tests ride on.
    Subscription::factory()->create([
        'business_id' => $business->id,
        'plan' => 'negocio',
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => null,
    ]);

    return $business;
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

    Mail::assertQueued(BusinessKnowledgeDigest::class, function (BusinessKnowledgeDigest $mail) use ($business): bool {
        $html = $mail->render();

        return $mail->hasTo($business->billing_email)
            && str_contains($html, 'Laboratorio Vida')
            && str_contains($html, '2 respuestas nuevas')
            && str_contains($html, '2 preguntas quedaron sin respuesta')
            && str_contains($html, '«¿Hacen envíos?»')
            && str_contains($html, route('assistant'));
    });

    // Never a WhatsApp out of the blue.
    Http::assertNothingSent();
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

    Mail::assertNothingQueued();
});

test('a business without a connected WhatsApp is skipped', function (): void {
    Queue::fake();

    $business = Business::factory()->create(['whatsapp_instance' => null]);
    KnowledgeSuggestion::factory()->askedIn(Conversation::factory()->create(['business_id' => $business->id]))->create(['business_id' => $business->id]);

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the unanswered line only rides along when something went unanswered', function (): void {
    Queue::fake();

    $business = knowledgeDigestBusiness();
    KnowledgeDocument::factory()->create([
        'business_id' => $business->id,
        'source_type' => 'faq',
    ]);

    $this->artisan('atendia:knowledge-digest')->assertSuccessful();

    Mail::assertQueued(BusinessKnowledgeDigest::class, fn (BusinessKnowledgeDigest $mail): bool => str_contains($mail->render(), '1 respuesta nueva')
        && ! str_contains($mail->render(), 'sin respuesta'));
});
