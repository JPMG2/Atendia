<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\CurrentStatus;
use Livewire\Wireable;

class CurrentStatusDto implements Wireable
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $name = ''
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
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
        );
    }

    public function toPayload(): array
    {
        return [
            'name' => CurrentStatus::normalizeName($this->name),
        ];
    }
}
