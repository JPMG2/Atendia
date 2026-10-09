<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\AdoptionNudge;

class AdoptionNudgeDto implements FormData
{
    public function __construct(
        public string $step = '',
        public string $subject = '',
        public string $body = '',
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
            'step' => $this->step,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: (string) ($data['step'] ?? ''),
            subject: $data['subject'] ?? '',
            body: $data['body'] ?? '',
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'step' => $this->step,
            'subject' => AdoptionNudge::normalizeSubject($this->subject),
            'body' => AdoptionNudge::normalizeBody($this->body),
            'is_active' => $this->is_active,
        ];
    }
}
