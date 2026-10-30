<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrency;
use App\Actions\Catalog\UpdateCurrency;
use App\Dto\CurrencyDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Currency;
use App\Rules\AttributeValidator;

class CurrencyForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: CurrencyDto::class,
            model: Currency::class,
            create: CreateCurrency::class,
            update: UpdateCurrency::class,
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
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.currency.fields.code'), 'hint' => __('catalog.currency.fields.code_hint'), 'required' => true, 'max' => 3, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.currency.fields.name'), 'placeholder' => __('catalog.currency.fields.name_placeholder'), 'required' => true],
            ['key' => 'symbol', 'type' => 'text', 'label' => __('catalog.currency.fields.symbol'), 'hint' => __('catalog.currency.fields.symbol_hint'), 'required' => true, 'max' => 5, 'mono' => true],
            ['key' => 'decimal_places', 'type' => 'number', 'label' => __('catalog.currency.fields.decimals'), 'min' => 0, 'max' => 2],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.currency.fields.status'), 'on_label' => __('catalog.currency.status.active'), 'off_label' => __('catalog.currency.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'currencies', 'code', $excludeId),
                'size:3',
            ],

            'name' => AttributeValidator::stringValid(true, '3'),

            'symbol' => [
                ...AttributeValidator::stringValid(true, '1'),
                'max:5',
            ],

            'decimal_places' => [
                ...AttributeValidator::numericInteger(true, 0),
                'max:2',
            ],
            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'symbol' => config('nicename.symbol'),
            'decimal_places' => config('nicename.decimal_places'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
