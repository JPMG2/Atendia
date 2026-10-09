<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;

/** The figures and switches of one plan, as the catalog form carries them. */
class PlanDto implements FormData
{
    /**
     * Every figure is nullable on purpose: emptying a number box must reach the
     * validator as "required", not kill the component with a TypeError.
     */
    public function __construct(
        public string $code = '',
        public ?int $price = null,
        public ?int $conversations_per_month = null,
        public ?int $team_seats = null,
        public ?int $messages_per_hour = null,
        public ?int $audio_minutes_per_month = null,
        public string $statistics = 'counts',
        public ?int $ask_per_month = null,
        public ?int $catalog_photos = null,
        public ?int $photos_per_item = null,
        public ?int $ai_alert_share = null,
        public ?int $trial_days = null,
        public bool $reads_media = false,
        public bool $departments = false,
        public bool $daily_digest = false,
        public bool $is_featured = false,
    ) {}

    public function toLivewire()
    {
        return $this->toArray();
    }

    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        // An empty box is null, never a zero: "no trial" and "required" both start there.
        $number = fn (string $key): ?int => ($data[$key] ?? null) === null || $data[$key] === '' ? null : (int) $data[$key];

        return new self(
            code: (string) ($data['code'] ?? ''),
            price: $number('price'),
            conversations_per_month: $number('conversations_per_month'),
            team_seats: $number('team_seats'),
            messages_per_hour: $number('messages_per_hour'),
            audio_minutes_per_month: $number('audio_minutes_per_month'),
            statistics: (string) ($data['statistics'] ?? 'counts'),
            ask_per_month: $number('ask_per_month'),
            catalog_photos: $number('catalog_photos'),
            photos_per_item: $number('photos_per_item'),
            ai_alert_share: $number('ai_alert_share'),
            trial_days: $number('trial_days'),
            reads_media: (bool) ($data['reads_media'] ?? false),
            departments: (bool) ($data['departments'] ?? false),
            daily_digest: (bool) ($data['daily_digest'] ?? false),
            is_featured: (bool) ($data['is_featured'] ?? false),
        );
    }

    /** The code is the key every row of billing points at: it is never written from the form. */
    public function toPayload(): array
    {
        return collect($this->toArray())->except('code')->all();
    }
}
