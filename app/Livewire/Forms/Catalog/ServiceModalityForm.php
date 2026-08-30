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

            // The `code` is the hinge with the code: globally unique, and what the
            // system matches on to know which behaviour applies. The column is
            // varchar(30), so the helper's max:255 is not enough.
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'name', $excludeId),

            // The column is nullable: without `nullable` a modality with no
            // description would bounce off the min in stringValid().
            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // The icon is not free text: it has to be a real key in config/icons.php
            // or <x-icon> would paint a hole.
            'icon' => [
                'nullable',
                'string',
                'in:'.implode(',', array_keys(config('icons'))),
            ],

            // The cap is the column's (smallint). Explicit because on an integer
            // `max` compares the VALUE, not the length.
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
