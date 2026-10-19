<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\DigestWriter;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Mail\BusinessDailyDigest;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A Wednesday: customer-facing sends rest on Sunday.
    $this->travelTo(now()->next('Wednesday')->setTime(20, 35));
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    Cache::flush();
    Mail::fake();
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
});

function digestBusiness(string $plan = 'negocio'): Business
{
    $business = Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'billing_email' => 'dueno@vida.test',
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '+54 9 299 552-9100',
        // A fixed clock: the digest leaves at LOCAL 20:30.
        'timezone' => 'UTC',
    ]);

    // The digest is sold from the middle plan up: on the entry plan the
    // command skips the business, which is the lock these tests ride on.
    Subscription::factory()->create([
        'business_id' => $business->id,
        'plan' => $plan,
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => null,
    ]);

    return $business;
}

/** @return list<array{from: string, q: string, a: string}> */
function digestTally(): array
{
    return [
        ['from' => '5491111111111', 'q' => '¿Turnos?', 'a' => 'Sí, mañana.'],
        ['from' => '5492222222222', 'q' => '¿Precio del perfil?', 'a' => 'Desde $20.'],
    ];
}

test('the owner receives the day digest by e-mail and nothing goes out on WhatsApp', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Tres consultas por turnos de laboratorio.']);
    Cache::put('wa:digest:'.$business->id, digestTally(), 3600);

    $this->artisan('atendia:daily-digest')->assertSuccessful();

    Mail::assertQueued(BusinessDailyDigest::class, fn (BusinessDailyDigest $mail): bool => $mail->hasTo('dueno@vida.test')
        && $mail->digest->messages === 2
        && $mail->digest->contacts === 2
        && $mail->digest->questions === ['¿Precio del perfil?', '¿Turnos?']
        && str_contains($mail->digest->summary, 'Tres consultas'));

    // Never a WhatsApp out of the blue: that is what the owner asks for with "resumen".
    Http::assertNothingSent();

    // Cleared once it left: tomorrow starts from zero.
    expect(Cache::get('wa:digest:'.$business->id))->toBeNull();
});

test('the mail renders its figures and the way to ask for another one', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Una consulta.']);

    $digest = app(App\Actions\Business\ComposeDailyDigest::class)->handle($business, digestTally());
    $html = (new BusinessDailyDigest($business, $digest))->render();

    expect($html)->toContain('Laboratorio Vida')
        ->toContain('¿Precio del perfil?')
        ->toContain(route('conversations'))
        ->toContain('«resumen»');
});

test('nothing automated reaches a business on Sunday, and it waits for Monday', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Una consulta.']);
    Cache::put('wa:digest:'.$business->id, [['from' => '5491111111111', 'q' => '¿Turnos?', 'a' => 'Sí.']], now()->addDays(10));

    $this->travelTo(now()->next('Sunday')->setTime(20, 35));
    $this->artisan('atendia:daily-digest')->assertSuccessful();
    Mail::assertNothingQueued();

    $this->travelTo(now()->next('Monday')->setTime(20, 35));
    $this->artisan('atendia:daily-digest')->assertSuccessful();
    Mail::assertQueuedCount(1);
});

test('the digest brags about this week referrals, and stays silent at zero', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Una consulta.']);

    session()->put('atendia_ref', $business->referral_code);
    Business::factory()->count(2)->create();
    session()->forget('atendia_ref');

    Cache::put('wa:digest:'.$business->id, [['from' => '5491111111111', 'q' => '¿Turnos?', 'a' => 'Sí, mañana.']], 3600);

    $this->artisan('atendia:daily-digest')->assertSuccessful();

    Mail::assertQueued(BusinessDailyDigest::class, fn (BusinessDailyDigest $mail): bool => $mail->digest->referred === 2
        && str_contains($mail->digest->toText(), trans_choice('assistant.digest.referrals', 2, ['count' => 2])));
});

test('a day with no conversations sends nothing', function (): void {
    digestBusiness();
    DigestWriter::fake(['nunca']);

    $this->artisan('atendia:daily-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the entry plan does not get the digest', function (): void {
    $business = digestBusiness('emprende');
    DigestWriter::fake(['nunca']);
    Cache::put('wa:digest:'.$business->id, digestTally(), 3600);

    $this->artisan('atendia:daily-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the owner writing resumen gets the day digest as a reply, and the tally stays for tonight', function (string $words): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Tres consultas por turnos de laboratorio.']);
    Cache::put('wa:digest:'.$business->id, digestTally(), 3600);

    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5492995529100', 'JPMG', $words, 'MSG-1'))->handle();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/message/sendText/atendia-demo')
        && $request['number'] === '5492995529100'
        && str_contains((string) $request['text'], '2 mensajes de 2 contactos')
        && str_contains((string) $request['text'], 'Tres consultas'));

    expect(Cache::get('wa:digest:'.$business->id))->not->toBeNull();
})->with(['resumen', 'Resumen', 'hola resumen', 'Hola, RESUMEN!']);

test('asking on an empty day says so instead of staying silent', function (): void {
    digestBusiness();

    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5492995529100', 'JPMG', 'resumen', 'MSG-2'))->handle();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'todavía no atendió'));
});

test('only the owner phone can ask, and only on a plan that sells the digest', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['nunca']);
    AsistenteAtendia::fake(['Claro, ¿en qué te ayudo?']);
    Cache::put('wa:digest:'.$business->id, digestTally(), 3600);

    // A customer saying the same word is just a customer: no digest leaves.
    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5491100000000', 'Cliente', 'resumen', 'MSG-3'))->handle();
    Http::assertNotSent(fn (Request $request): bool => str_contains((string) ($request['text'] ?? ''), 'mensajes de'));

    $business->subscription->update(['plan' => 'emprende']);
    Business::query()->find($business->id)?->touch();

    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5492995529100', 'JPMG', 'resumen', 'MSG-4'))->handle();
    Http::assertNotSent(fn (Request $request): bool => str_contains((string) ($request['text'] ?? ''), 'mensajes de'));
});
