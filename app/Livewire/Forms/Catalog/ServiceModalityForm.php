<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceModality;
use App\Actions\Catalog\UpdateServiceModality;
use App\Dto\ServiceModalityDto;
use App\Models\ServiceModality;
use App\Rules\AttributeValidator;

class ServiceModalityForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ServiceModalityDto::class,
            model: ServiceModality::class,
            create: CreateServiceModality::class,
            update: UpdateServiceModality::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // El `code` es la bisagra con el código: es único global y es lo que
            // el sistema matchea para saber qué comportamiento aplicar. Sin la
            // regla, un código repetido sale como crash de Postgres en vez de
            // error de campo. La columna es varchar(30): el max:255 no alcanza.
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'name', $excludeId),

            // La columna es nullable: sin el `nullable` una modalidad sin
            // descripción rebotaría contra el min de stringValid().
            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // El ícono no es texto libre: tiene que ser una clave real de
            // config/icons.php o <x-icon> pintaría un hueco.
            'icon' => [
                'nullable',
                'string',
                'in:'.implode(',', array_keys(config('icons'))),
            ],

            // El tope es el de la columna (smallint). Va explícito porque sobre
            // un entero `max` compara el VALOR, no el largo.
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
            'icon' => config('nicename.icon'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
