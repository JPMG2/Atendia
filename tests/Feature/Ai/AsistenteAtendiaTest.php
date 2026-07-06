<?php

use App\Ai\Agents\AsistenteAtendia;
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
