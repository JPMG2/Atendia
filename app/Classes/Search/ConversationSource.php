<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The tenant's threads. Words find the person; meaning finds the thread where
 * something was ASKED, over the questions' vectors the analysis already wrote.
 */
class ConversationSource implements SearchSource
{
    public string $group {
        get => 'search.groups.conversations';
    }

    public string $icon {
        get => 'message-circle';
    }

    public int $order {
        get => 4;
    }

    public function byWords(string $term, int $limit): Collection
    {
        // Who wrote it first, then what was written: a name is what the owner
        // usually has in mind, and a line inside the thread is the fallback.
        $byContact = Conversation::matching($term, $limit)
            ->map(fn (Conversation $thread): SearchHitDto => $this->hit(
                (int) $thread->id,
                (string) ($thread->contact_name ?? $thread->contact_phone),
                (string) ($thread->contact_phone ?? ''),
            ));

        $byBody = ConversationMessage::matching($term, $limit)
            ->map(fn (ConversationMessage $message): SearchHitDto => $this->hit(
                (int) $message->conversation_id,
                (string) ($message->conversation?->contact_name ?? $message->conversation?->contact_phone ?? ''),
                Str::limit(trim((string) $message->body), 70),
            ));

        return $byContact->concat($byBody)->unique('key')->values();
    }

    public function byMeaning(array $vector, int $limit): Collection
    {
        return ConversationQuestion::query()
            ->whereNotNull('embedding')
            ->whereNotNull('conversation_id')
            ->select(['id', 'conversation_id', 'question'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->with('conversation:id,contact_name,contact_phone')
            ->limit($limit)
            ->get()
            ->filter(fn (ConversationQuestion $asked): bool => $asked->conversation !== null)
            ->unique('conversation_id')
            ->values()
            ->map(fn (ConversationQuestion $asked): SearchHitDto => $this->hit(
                (int) $asked->conversation_id,
                (string) ($asked->conversation->contact_name ?? $asked->conversation->contact_phone),
                (string) $asked->question,
                semantic: true,
            ));
    }

    private function hit(int $id, string $who, string $about, bool $semantic = false): SearchHitDto
    {
        return new SearchHitDto(
            group: $this->group,
            icon: $this->icon,
            title: $who,
            subtitle: $about !== '' ? $about : __('search.subtitles.conversation'),
            url: route('conversations', ['hilo' => $id]),
            key: SearchHitDto::keyFor($this->group, $id),
            semantic: $semantic,
        );
    }
}
