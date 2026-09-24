<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Agents\MessageTriage;
use App\Classes\Main\Statistics;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Is this a real question?" — the word list plus the AI triage
|--------------------------------------------------------------------------
| The list drops the obvious small talk for free; the rest goes to the AI,
| which reads the exchange and decides whether it counts for statistics,
| whether the assistant needs teaching, and under which short topic.
*/

beforeEach(function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');
    Cache::flush();

    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);

    $this->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embedOne')
        ->andReturn(array_fill(0, 1536, 0.001))
        ->byDefault();

    $this->business = Business::factory()->create(['whatsapp_instance' => 'demo']);
});

function customerWrites(string $text, string $id = 'MSG-1'): ConversationMessage
{
    (new ProcessIncomingWhatsAppMessage('demo', '5491122334455', 'Carla', $text, $id))->handle();

    return ConversationMessage::query()->where('wa_message_id', $id)->sole();
}

test('the ai names the topic and flags what the assistant could not answer', function (): void {
    AsistenteAtendia::fake(['No pude confirmarlo, ¿querés que lo consulte con el equipo?', 'No pude confirmarlo, ¿querés que lo consulte con el equipo?']);
    MessageTriage::fake([['is_enquiry' => true, 'needs_teaching' => true, 'topic' => 'Análisis a domicilio']]);

    $message = customerWrites('¿Hacen análisis a domicilio?');

    expect($message->is_enquiry)->toBeTrue()
        ->and($message->needs_teaching)->toBeTrue()
        ->and($message->topic)->toBe('Análisis a domicilio')
        ->and($message->embedding)->not->toBeNull();

    // It judged the whole exchange, the assistant's answer included.
    MessageTriage::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '¿Hacen análisis a domicilio?')
        && str_contains($prompt->prompt, 'No pude confirmarlo'));
});

test('the ai sees what a list cannot: context turns a sentence into small talk', function (): void {
    AsistenteAtendia::fake(['¡Genial, te esperamos!', '¡Genial, te esperamos!']);
    MessageTriage::fake([['is_enquiry' => false, 'needs_teaching' => false, 'topic' => '']]);

    $message = customerWrites('Perfecto, paso mañana a las 10 entonces');

    expect($message->is_enquiry)->toBeFalse()
        ->and($message->topic)->toBeNull()
        ->and($message->embedding)->toBeNull();
});

test('the obvious small talk never costs an ai call', function (): void {
    AsistenteAtendia::fake(['¡Gracias a vos!', '¡Gracias a vos!']);
    MessageTriage::fake();

    $message = customerWrites('Ok, muchas gracias 🙌');

    expect($message->is_enquiry)->toBeFalse();
    MessageTriage::assertNeverPrompted();
});

test('a dead model keeps the list verdict instead of losing the message', function (): void {
    AsistenteAtendia::fake(['Abrimos de 8 a 12.', 'Abrimos de 8 a 12.']);
    MessageTriage::fake(fn () => throw new RuntimeException('model down'));

    $message = customerWrites('¿A qué hora abren?');

    expect($message->is_enquiry)->toBeTrue()
        ->and($message->needs_teaching)->toBeFalse();
});
