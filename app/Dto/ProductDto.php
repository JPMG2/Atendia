<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\Product;

/**
 * State of a product form ({@see Product}), full row on purpose: the import
 * fills what the sheet knows, the editor the rest, through this same shape.
 * `business_id` never travels here — a product is created THROUGH its owner,
 * so a request can never move it to another tenant.
 */
class ProductDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?int $service_type_id = null,
        public string $name = '',
        public ?string $code = null,
        public ?string $description = null,
        public ?string $price = null,
        public ?string $stock = null,
        /** @var array<int|string, mixed> Keyed by service_attribute_id. */
        public array $attribute_values = [],
        public bool $in_stock = true,
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
            'service_type_id' => $this->service_type_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'attribute_values' => $this->attribute_values,
            'in_stock' => $this->in_stock,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            service_type_id: DtoCast::toNullableId($data['service_type_id'] ?? null),
            name: $data['name'] ?? '',
            code: DtoCast::toNullableString($data['code'] ?? null),
            description: DtoCast::toNullableString($data['description'] ?? null),
            // Kept as strings: the columns are decimal and Eloquent casts
            // them to string too, so a float here would lose cents en route.
            price: DtoCast::toNullableString($data['price'] ?? null),
            stock: DtoCast::toNullableString($data['stock'] ?? null),
            attribute_values: is_array($data['attribute_values'] ?? null) ? $data['attribute_values'] : [],
            in_stock: $data['in_stock'] ?? true,
            is_active: $data['is_active'] ?? true,
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
            'name' => DtoCast::squish($this->name) ?? '',
            'code' => DtoCast::squish($this->code),
            'description' => DtoCast::squish($this->description),
            'price' => DtoCast::squish($this->price),
            'stock' => DtoCast::squish($this->stock),
            'attribute_values' => $this->attribute_values,
            'in_stock' => $this->in_stock,
            'is_active' => $this->is_active,
        ];
    }
}
