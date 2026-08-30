<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\ServiceAttribute;

class ServiceAttributeDto implements FormData
{
    /**
     * `options` travels as comma-separated text, which is what the admin types.
     * The column is jsonb: `toPayload()` turns it into a list with the model's
     * normaliser, in one place.
     */
    public function __construct(
        public string $code = '',
        public string $name = '',
        public ?string $description = null,
        public string $data_type = ServiceAttribute::FALLBACK_DATA_TYPE,
        public ?string $unit = null,
        public ?string $options = null,
        public bool $is_multiple = false,
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
            'data_type' => $this->data_type,
            'unit' => $this->unit,
            'options' => $this->options,
            'is_multiple' => $this->is_multiple,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        $options = $data['options'] ?? null;

        return new self(
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            description: DtoCast::toNullableString($data['description'] ?? null),
            data_type: (string) ($data['data_type'] ?? ServiceAttribute::FALLBACK_DATA_TYPE),
            unit: DtoCast::toNullableString($data['unit'] ?? null),
            // Editing hands over a list (the jsonb column); coming back from the
            // form, the text the admin typed. Both are accepted.
            options: DtoCast::toNullableString(is_array($options) ? implode(', ', $options) : $options),
            is_multiple: $data['is_multiple'] ?? false,
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'code' => ServiceAttribute::normalizeCode($this->code),
            'name' => ServiceAttribute::normalizeName($this->name),
            'description' => $this->description,
            'data_type' => $this->data_type,
            'unit' => $this->unit,
            // Only the list type uses options: anything else stores null rather
            // than an orphan list nobody will ever show.
            'options' => $this->data_type === 'list'
                ? ServiceAttribute::normalizeOptions($this->options)
                : null,
            'is_multiple' => $this->is_multiple,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
