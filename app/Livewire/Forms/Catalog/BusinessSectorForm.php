<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessSector;
use App\Actions\Catalog\UpdateBusinessSector;
use App\Dto\BusinessSectorDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;

class BusinessSectorForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.business_sector.fields.code'), 'hint' => __('catalog.business_sector.fields.code_hint'), 'required' => true, 'max' => 30, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.business_sector.fields.name'), 'placeholder' => __('catalog.business_sector.fields.name_placeholder'), 'required' => true],
            ['key' => 'description', 'type' => 'text', 'label' => __('catalog.business_sector.fields.description'), 'placeholder' => __('catalog.business_sector.fields.description_placeholder'), 'hint' => __('catalog.business_sector.fields.description_hint'), 'max' => 255],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.business_sector.fields.order'), 'hint' => __('catalog.business_sector.fields.order_hint'), 'min' => 0, 'max' => 32767],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.business_sector.fields.status'), 'on_label' => __('catalog.business_sector.status.active'), 'off_label' => __('catalog.business_sector.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_sectors', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'business_sectors', 'name', $excludeId),

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

    public static function fieldNames(): array
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
