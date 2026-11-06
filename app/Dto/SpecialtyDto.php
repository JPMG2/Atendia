<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\BusinessSector;

class SpecialtyDto implements FormData
{
    public function __construct(
        public ?int $business_activity_id = null,
        public string $code = '',
        public string $name = '',
        public int $sort_order = 0,
        public bool $is_active = true,
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
            'business_activity_id' => $this->business_activity_id,
            'code' => $this->code,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            business_activity_id: DtoCast::toNullableId($data['business_activity_id'] ?? null),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'business_activity_id' => $this->business_activity_id,
            // The code is the specialty's key, so it is lowercased; the name is kept as typed.
            'code' => BusinessSector::normalizeCode($this->code),
            'name' => BusinessSector::normalizeName($this->name),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
