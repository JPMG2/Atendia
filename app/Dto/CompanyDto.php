<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;

/**
 * El estado del formulario de la Compañía (AtendIa, el emisor de la factura).
 *
 * `country_id` y `province_id` NO son columnas de `companies`: la tabla guarda
 * solo `region_id`. Viven acá porque la pantalla pide el domicilio de lo general
 * a lo puntual (país → provincia → región) y esos dos campos necesitan estado
 * donde apoyarse. Por eso entran en `toArray()` (Livewire tiene que poder
 * hidratarlos) pero quedan FUERA de `toPayload()`: lo que se persiste es la
 * región, y el resto se vuelve a derivar de ella al cargar.
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
     * Solo las columnas de `companies`: país y provincia se quedan afuera.
     *
     * Los textos se limpian de espacios y los nullable vuelven a null, así una
     * columna vacía no queda con `''` — un valor "presente pero vacío" que
     * `whereNull` no encuentra nunca.
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
     * Colapsa los espacios de un texto escrito a mano y devuelve null si no
     * quedó nada. Mismo criterio que el `normalizeName` de los maestros.
     */
    private static function squish(?string $value): ?string
    {
        return DtoCast::toNullableString(
            trim((string) preg_replace('/\s+/u', ' ', (string) $value))
        );
    }
}
