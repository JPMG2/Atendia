<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\SupportReply;

class SupportReplyDto implements FormData
{
    public function __construct(
        public string $name = '',
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
            'name' => $this->name,
            'body' => $this->body,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            body: $data['body'] ?? '',
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'name' => SupportReply::normalizeName($this->name),
            'body' => SupportReply::normalizeBody($this->body),
            'is_active' => $this->is_active,
        ];
    }
}
