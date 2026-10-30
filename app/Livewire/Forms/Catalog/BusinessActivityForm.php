<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessActivity;
use App\Actions\Catalog\UpdateBusinessActivity;
use App\Dto\BusinessActivityDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;

class BusinessActivityForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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

        return self::fieldRules($excludeId, ['business_sector_id' => $data?->business_sector_id]);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.business_activity.fields.code'), 'hint' => __('catalog.business_activity.fields.code_hint'), 'required' => true, 'max' => 40, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.business_activity.fields.name'), 'placeholder' => __('catalog.business_activity.fields.name_placeholder'), 'required' => true],
            ['key' => 'business_sector_id', 'type' => 'select', 'label' => __('catalog.business_activity.fields.sector'), 'placeholder' => __('catalog.business_activity.fields.sector_placeholder'), 'required' => true, 'options' => BusinessSector::options()],
            ['key' => 'description', 'type' => 'text', 'label' => __('catalog.business_activity.fields.description'), 'placeholder' => __('catalog.business_activity.fields.description_placeholder'), 'hint' => __('catalog.business_activity.fields.description_hint'), 'max' => 255],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.business_activity.fields.order'), 'hint' => __('catalog.business_activity.fields.order_hint'), 'min' => 0, 'max' => 32767],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.business_activity.fields.status'), 'on_label' => __('catalog.business_activity.status.active'), 'off_label' => __('catalog.business_activity.status.inactive')],
            ['key' => 'catalog_eligible', 'type' => 'switch', 'label' => __('catalog.business_activity.fields.catalog'), 'hint' => __('catalog.business_activity.fields.catalog_hint'), 'on_label' => __('catalog.business_activity.catalog.on'), 'off_label' => __('catalog.business_activity.catalog.off')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'business_sector_id' => AttributeValidator::requireAndExists('business_sectors', 'id', 'business_sector_id', true),

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_activities', 'code', $excludeId),
                'max:40',
            ],

            'name' => AttributeValidator::requiredExistModelRelation(
                'business_activities',
                'name',
                'business_sector_id',
                $data['business_sector_id'] ?? null,
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

            'catalog_eligible' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'business_sector_id' => config('nicename.business_sector_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
            'catalog_eligible' => __('catalog.business_activity.fields.catalog'),
        ];
    }
}
