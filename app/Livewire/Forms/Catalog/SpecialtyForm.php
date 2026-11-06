<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSpecialty;
use App\Actions\Catalog\UpdateSpecialty;
use App\Dto\SpecialtyDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\BusinessActivity;
use App\Models\Specialty;
use App\Rules\AttributeValidator;

class SpecialtyForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: SpecialtyDto::class,
            model: Specialty::class,
            create: CreateSpecialty::class,
            update: UpdateSpecialty::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var SpecialtyDto|null $data */
        $data = $this->data;

        return self::fieldRules($excludeId, ['business_activity_id' => $data?->business_activity_id]);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.specialty.fields.code'), 'hint' => __('catalog.specialty.fields.code_hint'), 'required' => true, 'max' => 60, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.specialty.fields.name'), 'placeholder' => __('catalog.specialty.fields.name_placeholder'), 'required' => true],
            ['key' => 'business_activity_id', 'type' => 'select', 'label' => __('catalog.specialty.fields.activity'), 'placeholder' => __('catalog.specialty.fields.activity_placeholder'), 'hint' => __('catalog.specialty.fields.activity_hint'), 'required' => true, 'options' => BusinessActivity::options()],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.specialty.fields.order'), 'hint' => __('catalog.specialty.fields.order_hint'), 'min' => 0, 'max' => 32767],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.specialty.fields.status'), 'on_label' => __('catalog.specialty.status.active'), 'off_label' => __('catalog.specialty.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [
            'business_activity_id' => AttributeValidator::requireAndExists('business_activities', 'id', 'business_activity_id', true),

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'specialties', 'code', $excludeId),
                'max:60',
            ],

            'name' => AttributeValidator::requiredExistModelRelation(
                'specialties',
                'name',
                'business_activity_id',
                $data['business_activity_id'] ?? null,
                $excludeId,
            ),

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
            'business_activity_id' => config('nicename.business_activity_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
