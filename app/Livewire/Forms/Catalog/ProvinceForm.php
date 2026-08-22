<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateProvince;
use App\Actions\Catalog\UpdateProvince;
use App\Dto\ProvinceDto;
use App\Models\Province;
use App\Rules\AttributeValidator;

class ProvinceForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ProvinceDto::class,
            model: Province::class,
            create: CreateProvince::class,
            update: UpdateProvince::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var ProvinceDto|null $data */
        $data = $this->data;

        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin país reventaría en Postgres en vez de
            // marcar el campo.
            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            // El nombre es único DENTRO del país: "Córdoba" existe en Argentina
            // y en España, y las dos son válidas. Un unique global rechazaría la
            // segunda, y uno inexistente dejaría cargar Córdoba dos veces en el
            // mismo país. Por eso el unique va scopeado por country_id.
            'name' => AttributeValidator::requiredExistModelRelation(
                'provinces',
                'name',
                'country_id',
                $data?->country_id,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'country_id' => config('nicename.country_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
