<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\CountryHoliday;

/**
 * A holiday as the catalog form carries it. `kind` picks which of the three
 * date fields counts; the payload nulls the other two so a row never holds
 * two dates that disagree.
 */
class CountryHolidayDto implements FormData
{
    public function __construct(
        public ?int $country_id = null,
        public string $name = '',
        public string $kind = CountryHoliday::KIND_FIXED,
        public ?int $month = null,
        public ?int $day = null,
        public ?int $easter_offset = null,
        public ?string $on_date = null,
        public bool $is_active = true,
    ) {}

    public function toLivewire()
    {
        return $this->toArray();
    }

    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        $number = fn (string $key): ?int => ($data[$key] ?? null) === null || $data[$key] === '' ? null : (int) $data[$key];
        $onDate = ($data['on_date'] ?? null) === null || $data['on_date'] === '' ? null : substr((string) $data['on_date'], 0, 10);

        // Opening a saved row gives no `kind`: the date it holds says which shape it is.
        $kind = $data['kind'] ?? match (true) {
            $onDate !== null => CountryHoliday::KIND_ONCE,
            $number('easter_offset') !== null => CountryHoliday::KIND_EASTER,
            default => CountryHoliday::KIND_FIXED,
        };

        return new self(
            country_id: $number('country_id'),
            name: (string) ($data['name'] ?? ''),
            kind: (string) $kind,
            month: $number('month'),
            day: $number('day'),
            easter_offset: $number('easter_offset'),
            on_date: $onDate,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toPayload(): array
    {
        return [
            'country_id' => $this->country_id,
            'name' => trim((string) preg_replace('/\s+/u', ' ', $this->name)),
            'month' => $this->kind === CountryHoliday::KIND_FIXED ? $this->month : null,
            'day' => $this->kind === CountryHoliday::KIND_FIXED ? $this->day : null,
            'easter_offset' => $this->kind === CountryHoliday::KIND_EASTER ? $this->easter_offset : null,
            'on_date' => $this->kind === CountryHoliday::KIND_ONCE ? $this->on_date : null,
            'is_active' => $this->is_active,
        ];
    }
}
