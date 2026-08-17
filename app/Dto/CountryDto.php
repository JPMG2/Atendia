<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Country;
use Livewire\Wireable;

class CountryDto implements Wireable
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?int $currency_id = null,
        public string $name = '',
        public string $code = '',
        public ?string $phone_code = null,
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
            'currency_id' => $this->currency_id,
            'name' => $this->name,
            'code' => $this->code,
            'phone_code' => $this->phone_code,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            currency_id: self::toNullableId($data['currency_id'] ?? null),
            name: $data['name'] ?? '',
            code: $data['code'] ?? '',
            phone_code: self::toNullableString($data['phone_code'] ?? null),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'currency_id' => $this->currency_id,
            'name' => Country::normalizeName($this->name),
            'code' => Country::normalizeCode($this->code),
            'phone_code' => Country::normalizePhoneCode($this->phone_code),
            'is_active' => $this->is_active,
        ];
    }

    /**
     * El `<select>` de monedas manda el id como STRING ("3"), y este archivo corre
     * con `strict_types`: pasarlo tal cual al parámetro `?int` es un TypeError que
     * mata el componente (419, editor en blanco). El "sin moneda" llega como '',
     * y eso es null, no 0 — un 0 pasaría por `exists` como id inexistente.
     */
    private static function toNullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * El código telefónico es opcional: el input vacío llega como '' y la columna
     * es nullable, así que se guarda null en vez de una cadena vacía.
     */
    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
