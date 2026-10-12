<?php

declare(strict_types=1);

namespace App\Dto;

use App\Classes\Main\OpeningHours;
use App\Interfaces\Catalog\FormData;

class WhatsappVariantDto implements FormData
{
    public function __construct(
        public ?int $seasonal_window_id = null,
        public ?string $phone = null,
        /** Whether this season brings its own week; off = it inherits the company's. */
        public bool $override_schedule = false,
        /** @var array<int, array{open: bool, opens_at: string, closes_at: string}> */
        public array $opening_schedule = [],
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
            'seasonal_window_id' => $this->seasonal_window_id,
            'phone' => $this->phone,
            'override_schedule' => $this->override_schedule,
            'opening_schedule' => $this->opening_schedule,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * A stored week (even an empty one, "closed all week") means the season
     * overrides; null means it inherits. The form's own state says it outright.
     */
    public static function fromArray(array $data): self
    {
        $stored = is_array($data['opening_schedule'] ?? null) ? $data['opening_schedule'] : null;

        return new self(
            seasonal_window_id: DtoCast::toNullableId($data['seasonal_window_id'] ?? null),
            phone: DtoCast::squish($data['phone'] ?? null),
            override_schedule: array_key_exists('override_schedule', $data) ? (bool) $data['override_schedule'] : $stored !== null,
            opening_schedule: OpeningHours::formState($stored),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'seasonal_window_id' => $this->seasonal_window_id,
            'phone' => DtoCast::squish($this->phone),
            // Overriding with nothing open is a statement: closed all week.
            'opening_schedule' => $this->override_schedule ? OpeningHours::toStored($this->opening_schedule) : null,
            'is_active' => $this->is_active,
        ];
    }
}
