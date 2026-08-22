<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessSector;
use App\Actions\Catalog\UpdateBusinessSector;
use App\Dto\BusinessSectorDto;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;

class BusinessSectorForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: BusinessSectorDto::class,
            model: BusinessSector::class,
            create: CreateBusinessSector::class,
            update: UpdateBusinessSector::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La clave del rubro es única GLOBAL en la tabla y es lo que van a
            // referenciar los perfiles del asistente: sin la regla, un código
            // repetido sale como crash de Postgres en vez de error de campo.
            // La columna es varchar(30), así que el max:255 del helper no alcanza.
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_sectors', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'business_sectors', 'name', $excludeId),

            // La columna es nullable: sin el `nullable` un rubro sin descripción
            // rebotaría contra el min de stringValid().
            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // El tope es el de la columna (smallint). Va explícito porque sobre un
            // entero `max` compara el VALOR, no el largo.
            'sort_order' => [
                ...AttributeValidator::numericInteger(true, 0),
                'max:32767',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
