<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\CurrentStatus;

class CurrentStatusDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $name = '',
        public string $color = CurrentStatus::DEFAULT_COLOR
    ) {}

    /**
     * Prepare the object for Livewire.
     */
    public function toLivewire()
    {
        return $this->toArray();
    }

    /**
     * Recreate the object from Livewire data.
     */
    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            color: $data['color'] ?: CurrentStatus::DEFAULT_COLOR,
        );
    }

    public function toPayload(): array
    {
        return [
            'name' => CurrentStatus::normalizeName($this->name),
            'color' => $this->color,
        ];
    }
}
