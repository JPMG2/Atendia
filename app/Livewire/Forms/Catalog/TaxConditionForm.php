<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateTaxCondition;
use App\Actions\Catalog\UpdateTaxCondition;
use App\Dto\TaxConditionDto;
use App\Models\TaxCondition;
use App\Rules\AttributeValidator;

class TaxConditionForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: TaxConditionDto::class,
            model: TaxCondition::class,
            create: CreateTaxCondition::class,
            update: UpdateTaxCondition::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var TaxConditionDto|null $data */
        $data = $this->data;

        $countryId = $data?->country_id;

        return [

            // The FK is required on the table, so without `required` a row with no
            // country would blow up in Postgres instead of flagging the field.
            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            // The table is UNIQUE per country on both code and name: a standing
            // belongs to a country, so the same code can exist in two. A global unique
            // would reject the second; none at all would let a clash reach Postgres.
            'code' => AttributeValidator::requiredExistModelRelation(
                'tax_conditions',
                'code',
                'country_id',
                $countryId,
                $excludeId,
            ),

            'name' => AttributeValidator::requiredExistModelRelation(
                'tax_conditions',
                'name',
                'country_id',
                $countryId,
                $excludeId,
            ),

            'discriminate_tax' => AttributeValidator::booleanValue(true),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'country_id' => config('nicename.country_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'discriminate_tax' => config('nicename.discriminate_tax'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
