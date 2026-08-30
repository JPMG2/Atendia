<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceType;
use App\Actions\Catalog\UpdateServiceType;
use App\Dto\ServiceTypeDto;
use App\Models\ServiceType;
use App\Rules\AttributeValidator;

class ServiceTypeForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ServiceTypeDto::class,
            model: ServiceType::class,
            create: CreateServiceType::class,
            update: UpdateServiceType::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // Globally unique: the key the assistant and the RAG reference. The
            // column is varchar(40), so the helper's max:255 is not enough.
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_types', 'code', $excludeId),
                'max:40',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_types', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // One modality, and required: it decides what the assistant asks. A type
            // without one would not know how to offer itself.
            'service_modality_id' => AttributeValidator::requireAndExists(
                'service_modalities', 'id', 'service_modality_id', true,
            ),

            // The sector is OPTIONAL and only groups the admin screen: who offers
            // the type is `activity_service_type`'s call, not this column.
            'business_sector_id' => AttributeValidator::requireAndExists(
                'business_sectors', 'id', 'business_sector_id', false,
            ),

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
            'service_modality_id' => config('nicename.service_modality_id'),
            'business_sector_id' => config('nicename.business_sector_id'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
