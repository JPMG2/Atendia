<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One turn of a thread. Files are only counted: serving them to a phone
 * needs its own tenant-checked route, which a later wave brings.
 *
 * @mixin ConversationMessage
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'author' => $this->author?->value,
            'kind' => $this->kind->value,
            'body' => $this->body,
            'transcript' => $this->media_text,
            'audio_seconds' => $this->audio_seconds,
            'attachments' => count($this->media ?? []),
            'sent_at' => $this->created_at,
        ];
    }
}
