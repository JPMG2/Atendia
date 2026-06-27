<?php

use App\Ai\Agents\AsistenteAtendia;

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
