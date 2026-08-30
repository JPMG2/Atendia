<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;

/**
 * State of the company form — AtendIa itself, the one issuing the invoice.
 *
 * `country_id` and `province_id` are not columns: the table stores `region_id`
 * alone. They live here because the screen asks for the address from broad to
 * narrow and those two need somewhere to stand. Hence they are in `toArray()`,
 * which Livewire hydrates from, but out of `toPayload()`.
 */
class CompanyDto implements FormData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $legal_name = '',
        public ?string $tagline = null,
        public ?int $country_id = null,
        public ?int $province_id = null,
        public ?int $region_id = null,
        public ?string $address = null,
        public ?int $tax_condition_id = null,
        public string $tax_id = '',
        public ?string $logo_path_light = null,
        public ?string $logo_path_dark = null,
        public ?string $text_copyright = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $web = null,
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
            'legal_name' => $this->legal_name,
            'tagline' => $this->tagline,
            'country_id' => $this->country_id,
            'province_id' => $this->province_id,
            'region_id' => $this->region_id,
            'address' => $this->address,
            'tax_condition_id' => $this->tax_condition_id,
            'tax_id' => $this->tax_id,
            'logo_path_light' => $this->logo_path_light,
            'logo_path_dark' => $this->logo_path_dark,
            'text_copyright' => $this->text_copyright,
            'email' => $this->email,
            'phone' => $this->phone,
            'web' => $this->web,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            legal_name: $data['legal_name'] ?? '',
            tagline: DtoCast::toNullableString($data['tagline'] ?? null),
            country_id: DtoCast::toNullableId($data['country_id'] ?? null),
            province_id: DtoCast::toNullableId($data['province_id'] ?? null),
            region_id: DtoCast::toNullableId($data['region_id'] ?? null),
            address: DtoCast::toNullableString($data['address'] ?? null),
            tax_condition_id: DtoCast::toNullableId($data['tax_condition_id'] ?? null),
            tax_id: $data['tax_id'] ?? '',
            logo_path_light: DtoCast::toNullableString($data['logo_path_light'] ?? null),
            logo_path_dark: DtoCast::toNullableString($data['logo_path_dark'] ?? null),
            text_copyright: DtoCast::toNullableString($data['text_copyright'] ?? null),
            email: DtoCast::toNullableString($data['email'] ?? null),
            phone: DtoCast::toNullableString($data['phone'] ?? null),
            web: DtoCast::toNullableString($data['web'] ?? null),
        );
    }

    /**
     * Columns only: country and province stay out.
     *
     * Text is trimmed and nullable columns go back to null, so an empty one never
     * holds `''` — present but empty, which `whereNull` never finds.
     */
    public function toPayload(): array
    {
        return [
            'legal_name' => self::squish($this->legal_name) ?? '',
            'tagline' => self::squish($this->tagline),
            'region_id' => $this->region_id,
            'address' => self::squish($this->address),
            'tax_condition_id' => $this->tax_condition_id,
            'tax_id' => self::squish($this->tax_id) ?? '',
            'logo_path_light' => DtoCast::toNullableString($this->logo_path_light),
            'logo_path_dark' => DtoCast::toNullableString($this->logo_path_dark),
            'text_copyright' => self::squish($this->text_copyright),
            'email' => self::squish($this->email),
            'phone' => self::squish($this->phone),
            'web' => self::squish($this->web),
        ];
    }

    /**
     * Collapses the whitespace of hand-typed text, null when nothing is left.
     * Same criterion as `normalizeName` in the catalog models.
     */
    private static function squish(?string $value): ?string
    {
        return DtoCast::toNullableString(
            trim((string) preg_replace('/\s+/u', ' ', (string) $value))
        );
    }
}
