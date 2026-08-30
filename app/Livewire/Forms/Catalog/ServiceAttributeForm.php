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

            // Globally unique: the key the assistant and the pivot reference the
            // attribute by. The column is varchar(40).
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

            // Only the types config knows how to draw are valid. Once a service type
            // uses the attribute it stops being editable: changing it would break the
            // values already stored, which is what Drupal and commercetools do too.
            'data_type' => [
                'required',
                'string',
                'in:'.implode(',', $this->editableDataTypes($excludeId)),
            ],

            // The column is varchar(15) and a unit is a short symbol: without this
            // cap the helper's max:255 let it through and Postgres blew up.
            'unit' => [
                'nullable',
                'string',
                'max:15',
            ],

            // Already normalised to a list by the DTO, or null when the type is not
            // a list. The cap keeps an endless list out of a jsonb column.
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

    /**
     * Which data types validation accepts.
     *
     * The ones in config, unless the attribute is already in use: then the only
     * valid one is the one it has, so an attempt to change it surfaces as a
     * field error and not as broken values months later.
     *
     * @return array<int, string>
     */
    private function editableDataTypes(?int $excludeId): array
    {
        $all = array_keys(ServiceAttribute::dataTypes());

        if ($excludeId === null) {
            return $all;
        }

        $attribute = ServiceAttribute::query()->find($excludeId);

        if ($attribute === null || ! $attribute->isInUse()) {
            return $all;
        }

        return [$attribute->data_type];
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
