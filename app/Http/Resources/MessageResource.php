<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MessageAuthor;
use App\Models\ConversationMessage;
use Carbon\CarbonImmutable;
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
        $at = $this->created_at?->inBusinessTime();

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
            // Said as the web's thread says it, in the business's own clock.
            'text' => ($this->media ?? []) === [] ? $this->body : $this->textBesideMedia(),
            'chips' => array_map(fn (array $item): string => match ($item['kind'] ?? null) {
                'image' => __('assistant.media.photo_title'),
                'location' => ($item['label'] ?? '') !== '' ? $item['label'] : __('assistant.media.location_title'),
                default => $item['name'] ?? __('assistant.media.document_title'),
            }, $this->media ?? []),
            'time' => $at?->format('H:i') ?? '',
            'tag' => $this->author === MessageAuthor::Human ? __('client.conversations.human_tag') : null,
            'day' => $at === null ? '' : $this->dayLabel($at),
            'day_key' => $at?->toDateString() ?? '',
        ];
    }

    private function dayLabel(CarbonImmutable $day): string
    {
        return $day->isToday() ? __('client.conversations.day_today') : ($day->isYesterday() ? __('client.conversations.day_yesterday') : $day->format('d/m/Y'));
    }
}
