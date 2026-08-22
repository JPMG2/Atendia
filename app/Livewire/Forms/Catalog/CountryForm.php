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

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin moneda reventaría en Postgres en vez de
            // marcar el campo.
            'currency_id' => AttributeValidator::requireAndExists('currencies', 'id', 'currency_id', true),

            // `name` es UNIQUE en la tabla: sin la regla, un país repetido no
            // sería un error de campo sino un crash de base atrapado por
            // tryAction, es decir un toast vago en vez de un mensaje útil.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'countries', 'name', $excludeId),

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'countries', 'code', $excludeId),
                'size:3',
            ],

            // Opcional (la columna es nullable) y acotado a lo que pide la UI
            // (maxlength=6): sin el `nullable` un país sin código telefónico
            // rebotaría contra el `min:1` que trae digitValid().
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
