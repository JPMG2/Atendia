<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateRegion;
use App\Actions\Catalog\UpdateRegion;
use App\Dto\RegionDto;
use App\Models\Region;
use App\Rules\AttributeValidator;

class RegionForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: RegionDto::class,
            model: Region::class,
            create: CreateRegion::class,
            update: UpdateRegion::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var RegionDto|null $data */
        $data = $this->data;

        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin provincia reventaría en Postgres en vez de
            // marcar el campo.
            'province_id' => AttributeValidator::requireAndExists('provinces', 'id', 'province_id', true),

            // Único DENTRO de la provincia, no globalmente: el mismo nombre de
            // región se repite entre provincias distintas y las dos son válidas.
            'name' => AttributeValidator::requiredExistModelRelation(
                'regions',
                'name',
                'province_id',
                $data?->province_id,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'province_id' => config('nicename.province_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
