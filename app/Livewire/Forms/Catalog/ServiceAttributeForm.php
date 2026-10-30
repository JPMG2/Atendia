<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceAttribute;
use App\Actions\Catalog\UpdateServiceAttribute;
use App\Dto\ServiceAttributeDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\ServiceAttribute;
use App\Rules\AttributeValidator;

class ServiceAttributeForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    public static function phoneFields(): array
    {
        $types = collect(ServiceAttribute::dataTypes())->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all();

        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.service_attribute.fields.code'), 'hint' => __('catalog.service_attribute.fields.code_hint'), 'required' => true, 'max' => 40, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.service_attribute.fields.name'), 'placeholder' => __('catalog.service_attribute.fields.name_placeholder'), 'required' => true],
            ['key' => 'data_type', 'type' => 'select', 'label' => __('catalog.service_attribute.fields.data_type'), 'placeholder' => __('catalog.service_attribute.fields.data_type_placeholder'), 'hint' => __('catalog.service_attribute.fields.data_type_hint'), 'required' => true, 'options' => $types],
            ['key' => 'description', 'type' => 'text', 'label' => __('catalog.service_attribute.fields.description'), 'placeholder' => __('catalog.service_attribute.fields.description_placeholder'), 'hint' => __('catalog.service_attribute.fields.description_hint'), 'max' => 255],
            ['key' => 'unit', 'type' => 'text', 'label' => __('catalog.service_attribute.fields.unit'), 'placeholder' => __('catalog.service_attribute.fields.unit_placeholder'), 'hint' => __('catalog.service_attribute.fields.unit_hint'), 'max' => 15],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.service_attribute.fields.order'), 'hint' => __('catalog.service_attribute.fields.order_hint'), 'min' => 0, 'max' => 32767],
            ['key' => 'is_multiple', 'type' => 'switch', 'label' => __('catalog.service_attribute.fields.multiple'), 'on_label' => __('catalog.service_attribute.multiple.on'), 'off_label' => __('catalog.service_attribute.multiple.off')],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.service_attribute.fields.status'), 'on_label' => __('catalog.service_attribute.status.active'), 'off_label' => __('catalog.service_attribute.status.inactive')],
            ['key' => 'options', 'type' => 'text', 'label' => __('catalog.service_attribute.fields.options'), 'placeholder' => __('catalog.service_attribute.fields.options_placeholder'), 'hint' => __('catalog.service_attribute.fields.options_hint'), 'max' => 500],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

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

            'data_type' => [
                'required',
                'string',
                'in:'.implode(',', ServiceAttribute::dataTypesFor($excludeId)),
            ],

            'unit' => [
                'nullable',
                'string',
                'max:15',
            ],

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

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function fieldNames(): array
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
