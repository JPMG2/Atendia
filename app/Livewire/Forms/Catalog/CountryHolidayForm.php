<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCountryHoliday;
use App\Actions\Catalog\NotifyBridgeHolidays;
use App\Actions\Catalog\UpdateCountryHoliday;
use App\Dto\CountryHolidayDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Rules\AttributeValidator;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CountryHolidayForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId, ['kind' => $this->data?->kind, 'month' => $this->data?->month]);
    }

    /**
     * In the web form's order: `month` is there twice, once per shape that
     * uses it, and only the one of the chosen kind is on screen.
     */
    public static function phoneFields(): array
    {
        $words = 'catalog.country_holiday.fields.';
        $when = fn (string $kind): array => ['key' => 'kind', 'value' => $kind];

        return [
            ['key' => 'country_id', 'type' => 'select', 'label' => __($words.'country_id'), 'placeholder' => __($words.'country_placeholder'), 'required' => true, 'options' => Country::options(label: fn (Country $country): string => $country->name)],
            ['key' => 'name', 'type' => 'text', 'label' => __($words.'name'), 'placeholder' => __($words.'name_placeholder'), 'required' => true, 'max' => 80],
            ['key' => 'kind', 'type' => 'select', 'label' => __($words.'kind'), 'hint' => __($words.'kind_hint'), 'required' => true, 'options' => self::kindOptions()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __($words.'is_active'), 'on_label' => __('catalog.country_holiday.status.active'), 'off_label' => __('catalog.country_holiday.status.inactive')],
            ['key' => 'month', 'type' => 'select', 'label' => __($words.'month'), 'options' => self::monthOptions(), 'show_when' => $when(CountryHoliday::KIND_FIXED)],
            ['key' => 'day', 'type' => 'number', 'label' => __($words.'day'), 'min' => 1, 'max' => 31, 'show_when' => $when(CountryHoliday::KIND_FIXED)],
            ['key' => 'nth', 'type' => 'select', 'label' => __($words.'nth'), 'options' => self::nthOptions(), 'show_when' => $when(CountryHoliday::KIND_NTH)],
            ['key' => 'weekday', 'type' => 'select', 'label' => __($words.'weekday'), 'options' => self::weekdayOptions(), 'show_when' => $when(CountryHoliday::KIND_NTH)],
            ['key' => 'month', 'type' => 'select', 'label' => __($words.'month'), 'hint' => __($words.'nth_hint'), 'options' => self::monthOptions(), 'show_when' => $when(CountryHoliday::KIND_NTH)],
            ['key' => 'easter_offset', 'type' => 'number', 'label' => __($words.'easter_offset'), 'hint' => __($words.'easter_offset_hint'), 'min' => -60, 'max' => 70, 'show_when' => $when(CountryHoliday::KIND_EASTER)],
            ['key' => 'on_date', 'type' => 'date', 'label' => __($words.'on_date'), 'hint' => __($words.'on_date_hint'), 'show_when' => $when(CountryHoliday::KIND_ONCE)],
        ];
    }

    /**
     * @param  array{kind?: ?string, month?: ?int}  $data  The shape being saved: it decides which date is demanded.
     */
    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        $kind = $data['kind'] ?? null;
        $month = $data['month'] ?? null;

        return [
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            // The web's browser refuses markup before sending; the server must too, for the phone.
            'name' => ['string', ...AttributeValidator::stringValid(true, '3'), 'max:80'],
            // Not a column: the phone's editor validates it with the payload, the web never sends a free one.
            'kind' => ['sometimes', Rule::in(array_column(self::kindOptions(), 'value'))],
            // The payload nulls what the kind does not use, so only the date of its own shape is demanded.
            'month' => [Rule::requiredIf(in_array($kind, [CountryHoliday::KIND_FIXED, CountryHoliday::KIND_NTH], true)), 'nullable', 'integer', 'between:1,12'],
            'day' => [
                Rule::requiredIf($kind === CountryHoliday::KIND_FIXED),
                'nullable',
                'integer',
                'between:1,31',
                // 31 de abril does not exist; 29 de febrero does, in a leap year.
                fn (string $attribute, mixed $value, Closure $fail): mixed => $kind === CountryHoliday::KIND_FIXED && $month !== null && ! checkdate((int) $month, (int) $value, 2024)
                    ? $fail(__('catalog.country_holiday.errors.no_such_day'))
                    : null,
            ],
            'easter_offset' => [Rule::requiredIf($kind === CountryHoliday::KIND_EASTER), 'nullable', 'integer', 'between:-60,70'],
            'on_date' => [Rule::requiredIf($kind === CountryHoliday::KIND_ONCE), 'nullable', 'date_format:Y-m-d'],
            // A fifth Monday does not exist every month: only 1 to 4, or the last one, which always does.
            'weekday' => [Rule::requiredIf($kind === CountryHoliday::KIND_NTH), 'nullable', 'integer', 'between:0,6'],
            'nth' => [Rule::requiredIf($kind === CountryHoliday::KIND_NTH), 'nullable', 'integer', Rule::in([1, 2, 3, 4, CountryHoliday::LAST])],
            'is_active' => ['boolean'],
        ];
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function fieldNames(): array
    {
        return collect(['country_id', 'name', 'kind', 'month', 'day', 'easter_offset', 'on_date', 'weekday', 'nth', 'is_active'])
            ->mapWithKeys(fn (string $field): array => [$field => __('catalog.country_holiday.fields.'.$field)])
            ->all();
    }

    /**
     * What to ask after a save, web and phone alike: a one-year day that is new,
     * moved or switched back on is news for the businesses of its country.
     *
     * @return array{marked: list<string>, title: string, message: string, accept: string, dismiss: string}|null
     */
    public static function offerAfterSave(?CountryHoliday $before, CountryHoliday $saved): ?array
    {
        $date = $saved->on_date?->toDateString();
        $unchanged = $before !== null && $before->is_active && $before->on_date?->toDateString() === $date && $before->country_id === $saved->country_id;

        return $date === null || $unchanged ? null : app(NotifyBridgeHolidays::class)->offerFor($saved);
    }

    /** @return list<array{value: string, label: string}> */
    public static function kindOptions(): array
    {
        return collect([CountryHoliday::KIND_FIXED, CountryHoliday::KIND_NTH, CountryHoliday::KIND_EASTER, CountryHoliday::KIND_ONCE])
            ->map(fn (string $kind): array => ['value' => $kind, 'label' => __('catalog.country_holiday.kinds.'.$kind)])
            ->all();
    }

    /**
     * "First", "second"… "last": a fifth Monday is not there every month, so it is not offered.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function nthOptions(): array
    {
        return collect([1, 2, 3, 4, CountryHoliday::LAST])
            // Multibyte-safe: plain ucfirst left "último" in lower case.
            ->map(fn (int $nth): array => ['value' => $nth, 'label' => Str::ucfirst(__('catalog.country_holiday.nth.'.$nth))])
            ->all();
    }

    /**
     * Monday first, the way the week is read.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function weekdayOptions(): array
    {
        return collect([1, 2, 3, 4, 5, 6, 0])
            ->map(fn (int $day): array => ['value' => $day, 'label' => __('catalog.country_holiday.weekdays.'.$day)])
            ->all();
    }

    /** @return list<array{value: int, label: string}> */
    public static function monthOptions(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): array => ['value' => $month, 'label' => __('catalog.country_holiday.months.'.$month)])
            ->all();
    }
}
