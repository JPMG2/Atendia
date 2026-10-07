<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\DemoTag;

class DemoTagDto implements FormData
{
    public function __construct(
        public string $slug = '',
        public ?int $seasonal_window_id = null,
        public ?string $label = null,
        public ?string $business_name = null,
        public ?string $noun = null,
        public ?string $chips = null,
        public ?string $pool = null,
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
            'slug' => $this->slug,
            'seasonal_window_id' => $this->seasonal_window_id,
            'label' => $this->label,
            'business_name' => $this->business_name,
            'noun' => $this->noun,
            'chips' => $this->chips,
            'pool' => $this->pool,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * The two JSON columns are edited as plain text, one entry per line: a
     * grid of inputs for four bubbles is worse to write in than the four lines
     * themselves, and this is content she rewrites whole, not field by field.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'] ?? '',
            seasonal_window_id: DtoCast::toNullableId($data['seasonal_window_id'] ?? null),
            label: DtoCast::squish($data['label'] ?? null),
            business_name: DtoCast::squish($data['business_name'] ?? null),
            noun: DtoCast::squish($data['noun'] ?? null),
            chips: is_array($data['chips'] ?? null) ? DemoTag::chipsToText($data['chips']) : DtoCast::toNullableString($data['chips'] ?? null),
            pool: is_array($data['pool'] ?? null) ? DemoTag::scriptToText($data['pool']) : DtoCast::toNullableString($data['pool'] ?? null),
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        return [
            'slug' => DemoTag::normalizeSlug($this->slug),
            'seasonal_window_id' => $this->seasonal_window_id,
            'label' => DemoTag::normalizeLabel($this->label),
            'business_name' => DemoTag::normalizeLabel($this->business_name),
            'noun' => DemoTag::normalizeLabel($this->noun),
            'chips' => DemoTag::textToChips($this->chips),
            'pool' => DemoTag::textToScript($this->pool),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
