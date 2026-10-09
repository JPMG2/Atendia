<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCountryHoliday;
use App\Actions\Catalog\UpdateCountryHoliday;
use App\Dto\CountryHolidayDto;
use App\Models\CountryHoliday;
use Closure;
use Illuminate\Validation\Rule;

class CountryHolidayForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: CountryHolidayDto::class,
            model: CountryHoliday::class,
            create: CreateCountryHoliday::class,
            update: UpdateCountryHoliday::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $kind = $this->data?->kind;

        return [
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            // The payload nulls what the kind does not use, so only the date of its own shape is demanded.
            'month' => [Rule::requiredIf($kind === CountryHoliday::KIND_FIXED), 'nullable', 'integer', 'between:1,12'],
            'day' => [
                Rule::requiredIf($kind === CountryHoliday::KIND_FIXED),
                'nullable',
                'integer',
                'between:1,31',
                // 31 de abril does not exist; 29 de febrero does, in a leap year.
                fn (string $attribute, mixed $value, Closure $fail): mixed => $kind === CountryHoliday::KIND_FIXED && $this->data?->month !== null && ! checkdate((int) $this->data->month, (int) $value, 2024)
                    ? $fail(__('catalog.country_holiday.errors.no_such_day'))
                    : null,
            ],
            'easter_offset' => [Rule::requiredIf($kind === CountryHoliday::KIND_EASTER), 'nullable', 'integer', 'between:-60,70'],
            'on_date' => [Rule::requiredIf($kind === CountryHoliday::KIND_ONCE), 'nullable', 'date_format:Y-m-d'],
            'is_active' => ['boolean'],
        ];
    }

    protected function getValidationAttributes(): array
    {
        return collect(['country_id', 'name', 'month', 'day', 'easter_offset', 'on_date', 'is_active'])
            ->mapWithKeys(fn (string $field): array => [$field => __('catalog.country_holiday.fields.'.$field)])
            ->all();
    }
}
