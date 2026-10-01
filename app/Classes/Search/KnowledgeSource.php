<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the assistant knows. The meaning lane reads the same chunks the
 * assistant answers from, so what the owner finds here is what a customer
 * would be told — the whole point of letting her search it.
 */
class KnowledgeSource implements SearchSource
{
    public string $group {
        get => 'search.groups.knowledge';
    }

    public string $icon {
        get => 'book-open';
    }

    public int $order {
        get => 5;
    }

    public function byWords(string $term, int $limit): Collection
    {
        return KnowledgeDocument::matching($term, $limit)
            ->map(fn (KnowledgeDocument $doc): SearchHitDto => $this->hit(
                (int) $doc->id,
                (string) $doc->title,
                (string) $doc->content,
            ))
            ->values();
    }

    public function byMeaning(array $vector, int $limit): Collection
    {
        return KnowledgeChunk::query()
            ->select(['id', 'knowledge_document_id', 'content'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->with('document:id,title')
            ->limit($limit)
            ->get()
            ->filter(fn (KnowledgeChunk $chunk): bool => $chunk->document !== null)
            ->unique('knowledge_document_id')
            ->values()
            ->map(fn (KnowledgeChunk $chunk): SearchHitDto => $this->hit(
                (int) $chunk->knowledge_document_id,
                (string) $chunk->document->title,
                (string) $chunk->content,
                semantic: true,
            ));
    }

    private function hit(int $id, string $title, string $content, bool $semantic = false): SearchHitDto
    {
        return new SearchHitDto(
            group: $this->group,
            icon: $this->icon,
            title: $title !== '' ? $title : __('search.subtitles.knowledge'),
            subtitle: Str::limit(trim(preg_replace('/\s+/', ' ', $content) ?? ''), 80),
            url: route('assistant'),
            key: SearchHitDto::keyFor($this->group, $id),
            semantic: $semantic,
        );
    }
}
