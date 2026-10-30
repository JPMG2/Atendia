<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceModality;
use App\Actions\Catalog\UpdateServiceModality;
use App\Classes\Main\Icons;
use App\Dto\ServiceModalityDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\ServiceModality;
use App\Rules\AttributeValidator;

class ServiceModalityForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.service_modality.fields.code'), 'hint' => __('catalog.service_modality.fields.code_hint'), 'required' => true, 'max' => 30, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.service_modality.fields.name'), 'placeholder' => __('catalog.service_modality.fields.name_placeholder'), 'required' => true],
            ['key' => 'description', 'type' => 'text', 'label' => __('catalog.service_modality.fields.description'), 'placeholder' => __('catalog.service_modality.fields.description_placeholder'), 'hint' => __('catalog.service_modality.fields.description_hint'), 'max' => 255],
            ['key' => 'icon', 'type' => 'select', 'label' => __('catalog.service_modality.fields.icon'), 'placeholder' => __('catalog.service_modality.fields.icon_placeholder'), 'hint' => __('catalog.service_modality.fields.icon_hint'), 'options' => Icons::options()],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.service_modality.fields.order'), 'hint' => __('catalog.service_modality.fields.order_hint'), 'min' => 0, 'max' => 32767],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.service_modality.fields.status'), 'on_label' => __('catalog.service_modality.status.active'), 'off_label' => __('catalog.service_modality.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_modalities', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            'icon' => [
                'nullable',
                'string',
                'in:'.implode(',', array_keys(config('icons'))),
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
            'icon' => config('nicename.icon'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
