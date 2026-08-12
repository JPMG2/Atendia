<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Currency;
use Livewire\Wireable;

class CurrencyDto implements Wireable
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $code = '',
        public string $name = '',
        public string $symbol = '',
        public ?int $decimal_places = 2,
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
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimal_places,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            symbol: $data['symbol'] ?? '',
            decimal_places: array_key_exists('decimal_places', $data) ? $data['decimal_places'] : 2,
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'code' => Currency::normalizeCode($this->code),
            'name' => Currency::normalizeName($this->name),
            'symbol' => Currency::normalizeSymbol($this->symbol),
            'decimal_places' => $this->decimal_places,
            'is_active' => $this->is_active,
        ];
    }
}
