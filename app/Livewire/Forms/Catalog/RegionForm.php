<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateRegion;
use App\Actions\Catalog\UpdateRegion;
use App\Dto\RegionDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Province;
use App\Models\Region;
use App\Rules\AttributeValidator;

class RegionForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: RegionDto::class,
            model: Region::class,
            create: CreateRegion::class,
            update: UpdateRegion::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        /** @var RegionDto|null $data */
        $data = $this->data;

        return self::fieldRules($excludeId, ['province_id' => $data?->province_id]);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.region.fields.name'), 'placeholder' => __('catalog.region.fields.name_placeholder'), 'required' => true],
            ['key' => 'province_id', 'type' => 'select', 'label' => __('catalog.region.fields.province'), 'placeholder' => __('catalog.region.fields.province_placeholder'), 'required' => true, 'options' => Province::options()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.region.fields.status'), 'on_label' => __('catalog.region.status.active'), 'off_label' => __('catalog.region.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'province_id' => AttributeValidator::requireAndExists('provinces', 'id', 'province_id', true),

            'name' => AttributeValidator::requiredExistModelRelation(
                'regions',
                'name',
                'province_id',
                $data['province_id'] ?? null,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'province_id' => config('nicename.province_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
