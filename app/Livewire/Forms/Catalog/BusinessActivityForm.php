<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessActivity;
use App\Actions\Catalog\UpdateBusinessActivity;
use App\Dto\BusinessActivityDto;
use App\Models\BusinessActivity;
use App\Rules\AttributeValidator;

class BusinessActivityForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: BusinessActivityDto::class,
            model: BusinessActivity::class,
            create: CreateBusinessActivity::class,
            update: UpdateBusinessActivity::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var BusinessActivityDto|null $data */
        $data = $this->data;

        return [

            // The FK is required on the table: without `required` a row with no
            // sector would blow up in Postgres instead of flagging the field.
            'business_sector_id' => AttributeValidator::requireAndExists('business_sectors', 'id', 'business_sector_id', true),

            // Globally unique on purpose: it is the key the assistant's profile for
            // the trade is looked up by. The column is varchar(40).
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_activities', 'code', $excludeId),
                'max:40',
            ],

            // The name is unique WITHIN the sector: the same one can exist under two
            // sectors, but not twice under one. A global unique would reject the
            // second; none at all would allow the duplicate.
            'name' => AttributeValidator::requiredExistModelRelation(
                'business_activities',
                'name',
                'business_sector_id',
                $data?->business_sector_id,
                $excludeId,
            ),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
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
            'business_sector_id' => config('nicename.business_sector_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
