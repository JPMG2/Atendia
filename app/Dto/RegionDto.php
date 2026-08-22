<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\Region;

class RegionDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?int $province_id = null,
        public string $name = '',
        public bool $is_active = true
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
            'province_id' => $this->province_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            province_id: DtoCast::toNullableId($data['province_id'] ?? null),
            name: $data['name'] ?? '',
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'province_id' => $this->province_id,
            'name' => Region::normalizeName($this->name),
            'is_active' => $this->is_active,
        ];
    }
}
