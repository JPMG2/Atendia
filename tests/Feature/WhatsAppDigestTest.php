<?php

declare(strict_types=1);

use App\Ai\Agents\DigestWriter;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    Cache::flush();
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
});

function digestBusiness(): Business
{
    return Business::factory()->create([
        'name' => 'Laboratorio Vida',
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '+54 9 299 552-9100',
    ]);
}

test('the owner receives the day digest on the fallback number', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Tres consultas por turnos de laboratorio.']);

    Cache::put('wa:digest:'.$business->id.':'.now()->format('Y-m-d'), [
        ['from' => '5491111111111', 'q' => '¿Turnos?', 'a' => 'Sí, mañana.'],
        ['from' => '5492222222222', 'q' => '¿Precio del perfil?', 'a' => 'Desde $20.'],
    ], 3600);

    $this->artisan('atendia:whatsapp-digest')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/message/sendText/atendia-demo')
            && $request['number'] === '5492995529100'
            && str_contains((string) $request['text'], 'Laboratorio Vida')
            && str_contains((string) $request['text'], '2 mensajes de 2 contactos')
            && str_contains((string) $request['text'], 'Tres consultas por turnos');
    });

    // Pulled, not read: tomorrow starts from zero.
    expect(Cache::get('wa:digest:'.$business->id.':'.now()->format('Y-m-d')))->toBeNull();
});

test('the digest brags about this week referrals, and stays silent at zero', function (): void {
    $business = digestBusiness();
    DigestWriter::fake(['• Una consulta.']);

    session()->put('atendia_ref', $business->referral_code);
    Business::factory()->count(2)->create();
    session()->forget('atendia_ref');

    Cache::put('wa:digest:'.$business->id.':'.now()->format('Y-m-d'), [
        ['from' => '5491111111111', 'q' => '¿Turnos?', 'a' => 'Sí, mañana.'],
    ], 3600);

    $this->artisan('atendia:whatsapp-digest')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/message/sendText/atendia-demo')
        && str_contains((string) $request['text'], trans_choice('assistant.digest.referrals', 2, ['count' => 2])));
});

test('a day with no conversations sends nothing', function (): void {
    digestBusiness();
    DigestWriter::fake(['nunca']);

    $this->artisan('atendia:whatsapp-digest')->assertSuccessful();

    Http::assertNothingSent();
});

test('a business without a fallback number is skipped', function (): void {
    $business = digestBusiness();
    $business->update(['fallback_whatsapp_number' => null]);
    DigestWriter::fake(['nunca']);

    Cache::put('wa:digest:'.$business->id.':'.now()->format('Y-m-d'), [
        ['from' => '549', 'q' => 'Hola', 'a' => 'Hola'],
    ], 3600);

    $this->artisan('atendia:whatsapp-digest')->assertSuccessful();

    Http::assertNothingSent();
});
