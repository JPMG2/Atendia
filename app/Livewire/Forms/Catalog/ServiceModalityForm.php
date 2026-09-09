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

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            'icon' => [
                'nullable',
                'string',
                'in:'.implode(',', array_keys(config('icons'))),
            ],

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
