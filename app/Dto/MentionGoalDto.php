<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\MentionGoal;

class MentionGoalDto implements FormData
{
    public function __construct(
        public ?int $threshold = null,
        public string $caption = '',
        public bool $is_active = true
    ) {}

    /** Prepare the object for Livewire. */
    public function toLivewire()
    {
        return $this->toArray();
    }

    /** Recreate the object from Livewire data. */
    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return [
            'threshold' => $this->threshold,
            'caption' => $this->caption,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            threshold: ($data['threshold'] ?? '') === '' ? null : (int) $data['threshold'],
            caption: $data['caption'] ?? '',
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'threshold' => $this->threshold,
            'caption' => MentionGoal::normalizeCaption($this->caption),
            'is_active' => $this->is_active,
        ];
    }
}
