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

            // The column is varchar(10) and the input caps at 5: without this the
            // max:255 from stringValid let through a symbol that then blew up in
            // Postgres.
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
