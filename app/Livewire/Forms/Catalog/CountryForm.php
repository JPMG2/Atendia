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

            // The FK is required on the table, so without `required` a row with no
            // currency would blow up in Postgres instead of flagging the field.
            'currency_id' => AttributeValidator::requireAndExists('currencies', 'id', 'currency_id', true),

            // `name` is UNIQUE on the table: without the rule a repeated country is
            // not a field error but a database crash caught by tryAction — a vague
            // toast instead of a useful message.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'countries', 'name', $excludeId),

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'countries', 'code', $excludeId),
                'size:3',
            ],

            // Optional and capped at what the UI asks for: without `nullable` a
            // country with no dialling code would bounce off the `min:1` in
            // digitValid().
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
            'phone_code' => config('nicename.phone_code'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
