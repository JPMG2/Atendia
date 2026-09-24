<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AiUsage;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\TranscriptionGenerated;

/**
 * Meters every AI call at the SDK's own events, so a new agent is counted
 * the day it ships. The SDK reports cached input apart from promptTokens:
 * reading only that field left the cached part out of every cost (2026-09-24).
 */
class RecordAiUsage
{
    public function handle(AgentPrompted|EmbeddingsGenerated|TranscriptionGenerated $event): void
    {
        // Metering must never break the call it measures.
        rescue(fn () => AiUsage::query()->create($this->row($event)));
    }

    /** @return array{kind: string, model: ?string, input_tokens: int, cached_tokens: int, output_tokens: int} */
    private function row(AgentPrompted|EmbeddingsGenerated|TranscriptionGenerated $event): array
    {
        return match (true) {
            $event instanceof AgentPrompted => [
                'kind' => class_basename($event->prompt->agent),
                'model' => $event->response->meta->model ?: $event->prompt->model,
                'input_tokens' => $event->response->usage->promptTokens + $event->response->usage->cacheWriteInputTokens,
                'cached_tokens' => $event->response->usage->cacheReadInputTokens,
                'output_tokens' => $event->response->usage->completionTokens,
            ],
            $event instanceof EmbeddingsGenerated => [
                'kind' => AiUsage::EMBEDDINGS,
                'model' => $event->model,
                'input_tokens' => $event->response->tokens,
                'cached_tokens' => 0,
                'output_tokens' => 0,
            ],
            default => [
                'kind' => AiUsage::TRANSCRIPTION,
                'model' => $event->model,
                'input_tokens' => $event->response->usage->promptTokens,
                'cached_tokens' => 0,
                'output_tokens' => $event->response->usage->completionTokens,
            ],
        };
    }
}
