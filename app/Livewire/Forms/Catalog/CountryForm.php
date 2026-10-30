<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCountry;
use App\Actions\Catalog\UpdateCountry;
use App\Dto\CountryDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Country;
use App\Models\Currency;
use App\Rules\AttributeValidator;

class CountryForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: CountryDto::class,
            model: Country::class,
            create: CreateCountry::class,
            update: UpdateCountry::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.country.fields.code'), 'hint' => __('catalog.country.fields.code_hint'), 'required' => true, 'max' => 3, 'mono' => true],
            ['key' => 'iso2', 'type' => 'text', 'label' => __('catalog.country.fields.iso2'), 'hint' => __('catalog.country.fields.iso2_hint'), 'required' => true, 'max' => 2, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.country.fields.name'), 'placeholder' => __('catalog.country.fields.name_placeholder'), 'required' => true],
            ['key' => 'phone_code', 'type' => 'text', 'label' => __('catalog.country.fields.phone_code'), 'hint' => __('catalog.country.fields.phone_code_hint'), 'max' => 6, 'mono' => true],
            ['key' => 'currency_id', 'type' => 'select', 'label' => __('catalog.country.fields.currency'), 'placeholder' => __('catalog.country.fields.currency_placeholder'), 'required' => true, 'options' => Currency::options()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.country.fields.status'), 'on_label' => __('catalog.country.status.active'), 'off_label' => __('catalog.country.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'currency_id' => AttributeValidator::requireAndExists('currencies', 'id', 'currency_id', true),

            'name' => AttributeValidator::uniqueIdNameLength('3', 'countries', 'name', $excludeId),

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'countries', 'code', $excludeId),
                'size:3',
            ],

            'iso2' => [
                ...AttributeValidator::uniqueAlpha(true, '2', false, 'countries', 'iso2', $excludeId),
                'size:2',
            ],

            'phone_code' => [
                'nullable',
                ...AttributeValidator::digitValid('1', false),
                'max:6',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'currency_id' => config('nicename.currency_id'),
            'name' => config('nicename.name'),
            'code' => config('nicename.code'),
            'iso2' => config('nicename.iso2'),
            'phone_code' => config('nicename.phone_code'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
