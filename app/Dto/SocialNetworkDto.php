<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\SocialNetwork;
use Livewire\Wireable;

class SocialNetworkDto implements Wireable
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $name = '',
        public string $url = '',
        public ?string $icon = null,
        public ?string $abbreviation = null,
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
            'name' => $this->name,
            'url' => $this->url,
            'icon' => $this->icon,
            'abbreviation' => $this->abbreviation,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            url: $data['url'] ?? '',
            icon: self::toNullableString($data['icon'] ?? null),
            abbreviation: self::toNullableString($data['abbreviation'] ?? null),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'name' => SocialNetwork::normalizeName($this->name),
            'url' => SocialNetwork::normalizeUrl($this->url),
            'icon' => SocialNetwork::normalizeIcon($this->icon),
            'abbreviation' => SocialNetwork::normalizeAbbreviation($this->abbreviation),
            'is_active' => $this->is_active,
        ];
    }

    /**
     * `icon` y `abbreviation` son columnas nullable, y los campos vacíos del form
     * llegan como '' (el combobox manda '' cuando no hay opción elegida). Sin esto
     * media tabla quedaría con cadenas vacías "presentes pero vacías", que
     * `whereNull` no encuentra nunca — misma lección que CountryDto::phone_code.
     */
    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
