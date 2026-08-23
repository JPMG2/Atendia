<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\ServiceAttribute;

class ServiceAttributeDto implements FormData
{
    /**
     * Create a new class instance.
     *
     * `options` viaja como TEXTO separado por coma, que es lo que escribe el
     * admin en el campo. La columna es jsonb: la conversión a lista la hace
     * `toPayload()` con el normalizador del modelo, en un solo lugar.
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
            // Al editar, el valor llega como LISTA (columna jsonb); al volver del
            // formulario, como el texto que escribió el admin. Las dos entran.
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
            // Solo la lista usa opciones: si el tipo es otro, se guardan en null
            // en vez de dejar una lista huérfana que nadie va a mostrar.
            'options' => $this->data_type === 'list'
                ? ServiceAttribute::normalizeOptions($this->options)
                : null,
            'is_multiple' => $this->is_multiple,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
