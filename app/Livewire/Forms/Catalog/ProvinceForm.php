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

            // The FK is required on the table, so without `required` a row with no
            // country would blow up in Postgres instead of flagging the field.
            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            // The name is unique WITHIN the country: the same province name exists
            // in two countries and both are valid. Hence the unique is scoped by
            // country_id.
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
