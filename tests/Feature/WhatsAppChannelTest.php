<?php

declare(strict_types=1);

use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsAppMessage;
use App\Models\Company;
use App\Services\EvolutionApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** A concrete message: its wording depends on the locale the ritual captured. */
class LocaleEchoMessage extends WhatsAppMessage
{
    public function text(): string
    {
        return app()->getLocale().': '.$this->model->getAttribute('legal_name');
    }
}

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.instance', 'atendia-demo');
});

test('the whatsapp channel refuses a message that is not a whatsapp message', function (): void {
    $company = Company::factory()->create();

    expect(fn () => new WhatsApp($company, ['5491122334455'], Company::class))
        ->toThrow(InvalidArgumentException::class, 'has to be a');
});

test('the whatsapp channel sends the text to every recipient through the configured instance', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);

    $company = Company::factory()->create(['legal_name' => 'AtendIa']);

    (new WhatsApp($company, ['5491111111111', '5492222222222'], LocaleEchoMessage::class))->send();

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/message/sendText/atendia-demo')
            && $request->hasHeader('apikey', 'test-key')
            && $request['number'] === '5491111111111'
            && str_contains((string) $request['text'], 'AtendIa');
    });
});

test('the message is built under the captured locale and the locale is restored', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([])]);

    $company = Company::factory()->create(['legal_name' => 'AtendIa']);

    App::setLocale('es_AR');
    (new WhatsApp($company, ['5491111111111'], LocaleEchoMessage::class))->send();
    App::setLocale('es');

    Http::assertSent(fn (Request $request): bool => str_starts_with((string) $request['text'], 'es_AR:'));
    expect(app()->getLocale())->toBe('es');
});

test('a dead evolution is reported without blowing up the caller', function (): void {
    Exceptions::fake();
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);

    $company = Company::factory()->create();

    (new WhatsApp($company, ['5491111111111'], LocaleEchoMessage::class))->send();

    Exceptions::assertReported(RequestException::class);
});

test('registering the webhook sends the secret as a header evolution will echo back', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response([])]);

    app(EvolutionApi::class)->registerWebhook(
        'atendia-demo',
        'http://atendia-app:8080/api/webhooks/evolution',
        ['MESSAGES_UPSERT'],
        'shared-secret',
    );

    Http::assertSent(function (Request $request): bool {
        $webhook = $request['webhook'];

        return str_ends_with($request->url(), '/webhook/set/atendia-demo')
            && $webhook['enabled'] === true
            && $webhook['url'] === 'http://atendia-app:8080/api/webhooks/evolution'
            && $webhook['events'] === ['MESSAGES_UPSERT']
            && $webhook['headers'] === ['X-Webhook-Secret' => 'shared-secret'];
    });
});
