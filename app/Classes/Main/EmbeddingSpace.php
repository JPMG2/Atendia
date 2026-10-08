<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\EmbeddingMigration;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Enums\Lab;

/**
 * The vector space the knowledge search lives in: which model made the vectors
 * and how long they are. Indexing and querying must share it, or similarity
 * means nothing, so every embedding goes through the one that is in force.
 */
final class EmbeddingSpace
{
    private const string CACHE_KEY = 'embedding.space';

    public function __construct(
        public readonly string $model,
        public readonly string $provider,
        public readonly int $dimensions,
    ) {}

    /** The provider as the SDK names it; one it does not know falls back to OpenAI, the only one wired. */
    public Lab $lab {
        get => Lab::tryFrom($this->provider) ?? Lab::OpenAI;
    }

    /**
     * The one in force. Read on every embedding, so cached: it only changes
     * when a change of model is switched on or undone, and both forget it.
     */
    public static function active(): self
    {
        $space = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $ruling = EmbeddingMigration::ruling();

            return $ruling === null
                ? [
                    'model' => (string) config('rag.embedding.model'),
                    'provider' => 'openai',
                    'dimensions' => (int) config('rag.embedding.dimensions'),
                ]
                : ['model' => $ruling->model, 'provider' => $ruling->provider, 'dimensions' => $ruling->dimensions];
        });

        return new self($space['model'], $space['provider'], $space['dimensions']);
    }

    /** Called whenever the model in force changes. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function is(self $other): bool
    {
        return $this->model === $other->model && $this->dimensions === $other->dimensions;
    }
}
