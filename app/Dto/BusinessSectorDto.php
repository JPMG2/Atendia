<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\BusinessSector;

class BusinessSectorDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $code = '',
        public string $name = '',
        public ?string $description = null,
        public int $sort_order = 0,
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            description: DtoCast::toNullableString($data['description'] ?? null),
            // The input sends the number as a string and the property is `int`:
            // under strict_types, skipping the cast is a TypeError.
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            // Normalised BEFORE validating: unique has to look at the value that
            // will be stored, or "SALUD" would pass and land as "salud", colliding.
            'code' => BusinessSector::normalizeCode($this->code),
            'name' => BusinessSector::normalizeName($this->name),
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
