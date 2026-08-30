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

            // Globally unique, and what the assistant's profiles reference: without
            // the rule a repeated code surfaces as a Postgres crash instead of a
            // field error. The column is varchar(30).
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_sectors', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'business_sectors', 'name', $excludeId),

            // The column is nullable: without `nullable` a sector with no description
            // would bounce off the min in stringValid().
            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
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
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
