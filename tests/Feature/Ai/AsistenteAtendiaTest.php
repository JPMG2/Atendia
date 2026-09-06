<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Models\Business;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;

test('the assistant is pinned to the OpenAI provider and gpt-4.1 model', function (): void {
    $reflection = new ReflectionClass(AsistenteAtendia::class);

    $provider = $reflection->getAttributes(Provider::class)[0]->newInstance();
    $model = $reflection->getAttributes(Model::class)[0]->newInstance();

    expect($provider->value)->toBe(Lab::OpenAI)
        ->and($model->value)->toBe('gpt-4.1');
});

test('el asistente responde un prompt del usuario', function (): void {
    AsistenteAtendia::fake(['¡Hola! Soy el asistente de AtendIa, ¿en qué te ayudo?']);

    $respuesta = AsistenteAtendia::make()->prompt('Hola');

    expect($respuesta->text)->toBe('¡Hola! Soy el asistente de AtendIa, ¿en qué te ayudo?');
});

test('registra el prompt enviado al asistente', function (): void {
    AsistenteAtendia::fake(['ok']);

    AsistenteAtendia::make()->prompt('¿Cuál es el horario de atención?');

    AsistenteAtendia::assertPrompted('¿Cuál es el horario de atención?');
});

test('an answer that skipped the knowledge search is re-asked with a firm reminder', function (): void {
    $business = Business::factory()->create();

    AsistenteAtendia::fake(['Creo que sí lo hacemos.', 'Confirmado: ofrecemos ecodoppler.']);

    $response = new AsistenteAtendia($business)->answer('¿Hacen ecodoppler?');

    // The caller receives the grounded second pass, never the improvised one.
    expect($response->text)->toBe('Confirmado: ofrecemos ecodoppler.');

    AsistenteAtendia::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Recordatorio del sistema')
        && str_contains($prompt->prompt, '¿Hacen ecodoppler?'));
});

test('without a business there is no knowledge tool and no re-ask', function (): void {
    AsistenteAtendia::fake(['¡Hola! ¿En qué te ayudo?']);

    $response = new AsistenteAtendia()->answer('Hola');

    expect($response->text)->toBe('¡Hola! ¿En qué te ayudo?');

    AsistenteAtendia::assertNotPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Recordatorio del sistema'));
});
