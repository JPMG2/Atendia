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
            // El input manda el número como string; la propiedad es `int` y el DTO
            // corre con strict_types, así que sin el cast es un TypeError.
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            // Se normaliza ANTES de validar: el unique tiene que mirar el mismo
            // valor que se va a guardar, o "SALUD" pasaría el unique y entraría
            // como "salud" chocando en la base.
            'code' => BusinessSector::normalizeCode($this->code),
            'name' => BusinessSector::normalizeName($this->name),
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
