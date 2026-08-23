<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\ServiceType;

class ServiceTypeDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $code = '',
        public string $name = '',
        public ?string $description = null,
        public ?int $service_modality_id = null,
        public ?int $business_sector_id = null,
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
            'service_modality_id' => $this->service_modality_id,
            'business_sector_id' => $this->business_sector_id,
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
            // Los combobox mandan el id como string y el DTO corre con
            // strict_types: sin el cast es un TypeError (419, editor en blanco).
            service_modality_id: DtoCast::toNullableId($data['service_modality_id'] ?? null),
            business_sector_id: DtoCast::toNullableId($data['business_sector_id'] ?? null),
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'code' => ServiceType::normalizeCode($this->code),
            'name' => ServiceType::normalizeName($this->name),
            'description' => $this->description,
            'service_modality_id' => $this->service_modality_id,
            'business_sector_id' => $this->business_sector_id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
