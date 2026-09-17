<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
});

test('an inbound text is answered by the assistant through the business instance', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);

    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);

    // Two canned turns: the fake carries no tool calls, so answer() always
    // re-asks once and the customer receives the second, grounded pass.
    AsistenteAtendia::fake(['Creo que sí.', 'Sí, mañana tenemos turnos desde las 9.']);

    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5491122334455', 'Carla', '¿Tienen turnos mañana?', 'MSG-1'))->handle();

    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/message/sendText/atendia-demo')
            && $request['number'] === '5491122334455'
            && $request['text'] === 'Sí, mañana tenemos turnos desde las 9.';
    });
});

test('the customer sees typing while the assistant thinks', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([])]);

    Business::factory()->create(['whatsapp_instance' => 'atendia-demo']);
    AsistenteAtendia::fake(['Un momento.', 'Listo.']);

    (new ProcessIncomingWhatsAppMessage('atendia-demo', '5491122334455', 'Carla', 'Hola', 'MSG-1'))->handle();

    // Presence goes first: it is the "we heard you" the reply then replaces.
    $urls = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->url());

    expect($urls->first())->toEndWith('/chat/sendPresence/atendia-demo')
        ->and($urls->last())->toEndWith('/message/sendText/atendia-demo');
});

test('a message for an unclaimed instance warns and answers nobody', function (): void {
    Http::fake();
    Log::spy();

    (new ProcessIncomingWhatsAppMessage('ghost-instance', '5491122334455', 'Carla', 'Hola', 'MSG-1'))->handle();

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message): bool => $message === 'whatsapp.incoming.unclaimed',
    )->once();
});
