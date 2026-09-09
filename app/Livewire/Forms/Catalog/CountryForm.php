<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCountry;
use App\Actions\Catalog\UpdateCountry;
use App\Dto\CountryDto;
use App\Models\Country;
use App\Rules\AttributeValidator;

class CountryForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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

    protected function getValidationAttributes(): array
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
