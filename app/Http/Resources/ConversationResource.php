<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A thread as the phone reads it. The list carries a short preview of the
 * last exchange; the opened thread carries its messages instead.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    private const int PREVIEW_LENGTH = 140;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $last = $this->relationLoaded('latestMessage') ? $this->latestMessage : null;
        $isThread = $this->relationLoaded('messages');
        $lastAt = $this->last_message_at?->inBusinessTime();

        return [
            'id' => $this->id,
            'name' => $this->contact_name,
            'phone' => $this->contact_phone,
            'status' => $this->status->value,
            // The web's words: a list names the contact alone, an opened thread prefers the customer's own name.
            'title' => ($isThread ? $this->customer?->displayName() : null) ?? $this->contact_name ?? __('client.conversations.anonymous'),
            'badge' => $this->badge($isThread),
            'when' => $lastAt === null ? '' : ($lastAt->isToday() ? $lastAt->format('H:i') : $lastAt->format('d/m')),
            'last_message_at' => $this->last_message_at,
            'attachments' => $this->when(
                array_key_exists('attachments_count', $this->resource->getAttributes()),
                fn (): int => (int) $this->getAttribute('attachments_count'),
            ),
            'attachments_label' => $this->when(
                array_key_exists('attachments_count', $this->resource->getAttributes()),
                fn (): string => trans_choice('client.conversations.files.count', (int) $this->getAttribute('attachments_count'), ['count' => (int) $this->getAttribute('attachments_count')]),
            ),
            'last_message' => $this->when($this->relationLoaded('latestMessage'), fn (): ?array => $last === null ? null : [
                'direction' => $last->direction->value,
                'author' => $last->author?->value,
                'kind' => $last->kind->value,
                'preview' => Str::limit((string) $last->body, self::PREVIEW_LENGTH - 1, '…'),
                'prefix' => $last->direction === MessageDirection::Out ? __('client.conversations.assistant_prefix') : null,
                'sent_at' => $last->created_at,
            ]),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }

    /**
     * What the web marks and when: the list flags only a thread waiting on the team,
     * the opened thread names the three states that are not the assistant's.
     *
     * @return array{label: string, tone: string}|null
     */
    private function badge(bool $isThread): ?array
    {
        return match (true) {
            $this->status === ConversationStatus::Team && ! $isThread => ['label' => __('client.conversations.status_team_chip'), 'tone' => 'accent'],
            $this->status === ConversationStatus::Team => ['label' => __('client.conversations.status_team'), 'tone' => 'warning'],
            $this->status === ConversationStatus::Customer && $isThread => ['label' => __('client.conversations.status_customer'), 'tone' => 'info'],
            $this->status === ConversationStatus::Resolved && $isThread => ['label' => __('client.conversations.status_resolved'), 'tone' => 'success'],
            default => null,
        };
    }
}
