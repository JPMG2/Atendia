<?php

declare(strict_types=1);

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'name' => $this->contact_name,
            'phone' => $this->contact_phone,
            'status' => $this->status->value,
            'last_message_at' => $this->last_message_at,
            'attachments' => $this->when(
                array_key_exists('attachments_count', $this->resource->getAttributes()),
                fn (): int => (int) $this->getAttribute('attachments_count'),
            ),
            'last_message' => $this->when($this->relationLoaded('latestMessage'), fn (): ?array => $last === null ? null : [
                'direction' => $last->direction->value,
                'author' => $last->author?->value,
                'kind' => $last->kind->value,
                'preview' => Str::limit((string) $last->body, self::PREVIEW_LENGTH - 1, '…'),
                'sent_at' => $last->created_at,
            ]),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
