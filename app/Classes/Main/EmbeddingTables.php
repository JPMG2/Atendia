<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\EmbeddingTableDto;
use App\Services\Topics\ActivityIntents;

/**
 * Every table that holds vectors. They all share one space — a question is
 * embedded once and compared against all of them — so a change of model has to
 * move them together, and this list is what says which ones there are.
 *
 * A new table with an `embedding` column joins here, or the next change of
 * model would leave it behind in a space nobody queries.
 */
final class EmbeddingTables
{
    /** @return list<EmbeddingTableDto> */
    public static function registered(): array
    {
        return [
            new EmbeddingTableDto('knowledge_chunks', ['content'], fn (object $row): string => (string) $row->content),
            new EmbeddingTableDto('services', ['name'], fn (object $row): string => (string) $row->name),
            new EmbeddingTableDto('products', ['name'], fn (object $row): string => (string) $row->name),
            new EmbeddingTableDto('help_articles', ['title', 'keywords', 'body'], fn (object $row): string => trim($row->title."\n".($row->keywords ?? '')."\n".$row->body)),
            new EmbeddingTableDto('conversation_questions', ['question'], fn (object $row): string => (string) $row->question),
            new EmbeddingTableDto('knowledge_suggestions', ['question'], fn (object $row): string => (string) $row->question),
            new EmbeddingTableDto('question_intents', ['name', 'description'], fn (object $row): string => ActivityIntents::meaning((string) $row->name, (string) $row->description), indexed: false),
        ];
    }

    public static function named(string $table): ?EmbeddingTableDto
    {
        return collect(self::registered())->first(fn (EmbeddingTableDto $dto): bool => $dto->table === $table);
    }
}
