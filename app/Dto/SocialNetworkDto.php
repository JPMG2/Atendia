<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\SocialNetwork;

class SocialNetworkDto implements FormData
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
            icon: DtoCast::toNullableString($data['icon'] ?? null),
            abbreviation: DtoCast::toNullableString($data['abbreviation'] ?? null),
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
}
