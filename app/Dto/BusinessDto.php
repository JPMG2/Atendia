<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\Business;

/**
 * State of the business forms — the TENANT ({@see Business}).
 *
 * Full row on purpose: wizard steps and the future profile edit slices of the
 * same record, each form hydrating from it first, so a partial screen can
 * never blank out what another one saved. `sector` is not a column — it feeds
 * the suggestions and the activity sync — so it stays out of `toPayload()`.
 */
class BusinessDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $name = '',
        public ?int $country_id = null,
        public ?int $province_id = null,
        public ?string $timezone = null,
        public string $billing_email = '',
        public ?string $whatsapp_number = null,
        public ?string $fallback_whatsapp_number = null,
        public ?string $email = null,
        public ?string $web = null,
        public bool $is_active = true,
        public ?string $sector = null,
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
            'country_id' => $this->country_id,
            'province_id' => $this->province_id,
            'timezone' => $this->timezone,
            'billing_email' => $this->billing_email,
            'whatsapp_number' => $this->whatsapp_number,
            'fallback_whatsapp_number' => $this->fallback_whatsapp_number,
            'email' => $this->email,
            'web' => $this->web,
            'is_active' => $this->is_active,
            'sector' => $this->sector,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            country_id: DtoCast::toNullableId($data['country_id'] ?? null),
            province_id: DtoCast::toNullableId($data['province_id'] ?? null),
            timezone: DtoCast::toNullableString($data['timezone'] ?? null),
            billing_email: $data['billing_email'] ?? '',
            whatsapp_number: DtoCast::toNullableString($data['whatsapp_number'] ?? null),
            fallback_whatsapp_number: DtoCast::toNullableString($data['fallback_whatsapp_number'] ?? null),
            email: DtoCast::toNullableString($data['email'] ?? null),
            web: DtoCast::toNullableString($data['web'] ?? null),
            is_active: $data['is_active'] ?? true,
            sector: DtoCast::toNullableString($data['sector'] ?? null),
        );
    }

    /**
     * Columns only: the sector stays out.
     *
     * Text is trimmed and nullable columns go back to null, so an empty one never
     * holds `''` — present but empty, which `whereNull` never finds.
     */
    public function toPayload(): array
    {
        return [
            'name' => DtoCast::squish($this->name) ?? '',
            'country_id' => $this->country_id,
            'province_id' => $this->province_id,
            'timezone' => DtoCast::squish($this->timezone),
            'billing_email' => DtoCast::squish($this->billing_email) ?? '',
            'whatsapp_number' => DtoCast::squish($this->whatsapp_number),
            'fallback_whatsapp_number' => DtoCast::squish($this->fallback_whatsapp_number),
            'email' => DtoCast::squish($this->email),
            'web' => DtoCast::squish($this->web),
            'is_active' => $this->is_active,
        ];
    }
}
