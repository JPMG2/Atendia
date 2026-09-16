<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\Service;

/**
 * State of a service form — what the tenant offers ({@see Service}).
 *
 * Full row on purpose: the wizard only captures the name, the service editor
 * fills the rest later, both through this same shape. `business_id` never
 * travels here — a service is created THROUGH its owner, so a request can
 * never move it to another tenant.
 */
class ServiceDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?int $service_type_id = null,
        public ?int $service_category_id = null,
        public string $name = '',
        public ?string $description = null,
        public ?string $prep_note = null,
        public string $price_type = 'fixed',
        public ?string $price = null,
        public ?string $deposit = null,
        public ?int $duration_minutes = null,
        public bool $is_active = true,
        public bool $is_featured = false,
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
            'service_type_id' => $this->service_type_id,
            'service_category_id' => $this->service_category_id,
            'name' => $this->name,
            'description' => $this->description,
            'prep_note' => $this->prep_note,
            'price_type' => $this->price_type,
            'price' => $this->price,
            'deposit' => $this->deposit,
            'duration_minutes' => $this->duration_minutes,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            service_type_id: DtoCast::toNullableId($data['service_type_id'] ?? null),
            service_category_id: DtoCast::toNullableId($data['service_category_id'] ?? null),
            name: $data['name'] ?? '',
            description: DtoCast::toNullableString($data['description'] ?? null),
            prep_note: DtoCast::toNullableString($data['prep_note'] ?? null),
            price_type: $data['price_type'] ?? 'fixed',
            // Kept as a string: the column is decimal and Eloquent casts it to
            // string too, so a float here would lose cents on the way through.
            price: DtoCast::toNullableString($data['price'] ?? null),
            deposit: DtoCast::toNullableString($data['deposit'] ?? null),
            duration_minutes: DtoCast::toNullableId($data['duration_minutes'] ?? null),
            is_active: $data['is_active'] ?? true,
            is_featured: $data['is_featured'] ?? false,
        );
    }

    /**
     * Text is trimmed and nullable columns go back to null, so an empty one never
     * holds `''` — present but empty, which `whereNull` never finds.
     */
    public function toPayload(): array
    {
        return [
            'service_type_id' => $this->service_type_id,
            'service_category_id' => $this->service_category_id,
            'name' => DtoCast::squish($this->name) ?? '',
            'description' => DtoCast::squish($this->description),
            'prep_note' => DtoCast::squish($this->prep_note),
            'price_type' => $this->price_type,
            // A free or to-be-agreed service carries no amount: keeping one
            // would make the assistant quote a price the type denies.
            'price' => in_array($this->price_type, ['free', 'talk'], true) ? null : DtoCast::squish($this->price),
            'deposit' => DtoCast::squish($this->deposit),
            'duration_minutes' => $this->duration_minutes,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
        ];
    }
}
