<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\MessageTriage;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Models\ConversationMessage;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * The AI half of "is this a real question?": runs after the exchange is
 * stored, so it reads what the assistant asked before and answered after.
 * Only real questions get a meaning vector — they alone feed "most asked".
 */
class TriageCustomerMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $businessId, public int $messageId) {}

    public function handle(KnowledgeEmbedder $embedder): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($embedder): void {
            $message = ConversationMessage::query()->find($this->messageId);

            if ($message === null || ! $message->is_enquiry) {
                return;
            }

            // A dead model must not lose the message: the list verdict stays.
            try {
                $verdict = MessageTriage::make()->prompt($this->exchangeFor($message));
            } catch (Throwable $e) {
                report($e);

                return;
            }

            $isEnquiry = (bool) ($verdict['is_enquiry'] ?? true);
            $topic = Str::limit(trim((string) ($verdict['topic'] ?? '')), 77);

            $message->forceFill([
                'is_enquiry' => $isEnquiry,
                'needs_teaching' => $isEnquiry && (bool) ($verdict['needs_teaching'] ?? false),
                'topic' => $isEnquiry && $topic !== '' ? $topic : null,
            ])->save();

            if ($isEnquiry && $message->embedding === null) {
                rescue(fn () => $message->forceFill(['embedding' => $embedder->embedOne($message->body)])->save(), report: false);
            }
        });
    }

    /** The message in context: what came right before and what the assistant answered. */
    private function exchangeFor(ConversationMessage $message): string
    {
        $thread = ConversationMessage::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('kind', MessageKind::Message);

        $before = (clone $thread)->where('id', '<', $message->id)->where('direction', MessageDirection::Out)->latest('id')->value('body');
        $after = (clone $thread)->where('id', '>', $message->id)->where('direction', MessageDirection::Out)->oldest('id')->value('body');

        return 'Lo que el negocio dijo antes: '.($before ?? '(nada, es el primer mensaje)')."\n\n"
            .'Mensaje del cliente: '.$message->body."\n\n"
            .'Respuesta del asistente: '.($after ?? '(sin respuesta: la charla estaba en manos del equipo)');
    }
}
