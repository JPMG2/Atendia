<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrency;
use App\Actions\Catalog\UpdateCurrency;
use App\Dto\CurrencyDto;
use App\Models\Currency;
use App\Rules\AttributeValidator;

class CurrencyForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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
        return [

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'currencies', 'code', $excludeId),
                'size:3',
            ],

            'name' => AttributeValidator::stringValid(true, '3'),

            // La columna es varchar(10) y el input pone maxlength=5: sin este tope
            // el max:255 que trae stringValid dejaba pasar un símbolo que después
            // reventaba en Postgres. 5 es lo que ya pide la UI ("$, US$, €").
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

    protected function getValidationAttributes(): array
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
