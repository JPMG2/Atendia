<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceAttribute;
use App\Actions\Catalog\UpdateServiceAttribute;
use App\Dto\ServiceAttributeDto;
use App\Models\ServiceAttribute;
use App\Rules\AttributeValidator;

class ServiceAttributeForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ServiceAttributeDto::class,
            model: ServiceAttribute::class,
            create: CreateServiceAttribute::class,
            update: UpdateServiceAttribute::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_attributes', 'code', $excludeId),
                'max:40',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('2', 'service_attributes', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            'data_type' => [
                'required',
                'string',
                'in:'.implode(',', ServiceAttribute::dataTypesFor($excludeId)),
            ],

            'unit' => [
                'nullable',
                'string',
                'max:15',
            ],

            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['string', 'max:60'],

            'is_multiple' => AttributeValidator::booleanValue(true),

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
            'data_type' => config('nicename.data_type'),
            'unit' => config('nicename.unit'),
            'options' => config('nicename.options'),
            'is_multiple' => config('nicename.is_multiple'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
