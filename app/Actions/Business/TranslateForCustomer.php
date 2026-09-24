<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Ai\Agents\ReplyTranslator;
use App\Models\Conversation;

/**
 * The business always writes in Spanish; the customer always reads their
 * own language. A failed translation falls back to the Spanish original,
 * never to silence.
 */
class TranslateForCustomer
{
    public function handle(Conversation $conversation, string $text): string
    {
        $language = strtolower((string) $conversation->language);

        if ($language === '' || str_starts_with($language, 'es')) {
            return $text;
        }

        return rescue(function () use ($language, $text): string {
            $response = new ReplyTranslator()->prompt(
                "Idioma del cliente: {$language}\n\nRespuesta del equipo (en español):\n{$text}",
            );

            $translated = trim((string) ($response['text'] ?? ''));

            return $translated !== '' ? $translated : $text;
        }, $text, report: false);
    }
}
