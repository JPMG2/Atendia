<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateProvince;
use App\Actions\Catalog\UpdateProvince;
use App\Dto\ProvinceDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Country;
use App\Models\Province;
use App\Rules\AttributeValidator;

class ProvinceForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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

        return self::fieldRules($excludeId, ['country_id' => $data?->country_id]);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.province.fields.name'), 'placeholder' => __('catalog.province.fields.name_placeholder'), 'required' => true],
            ['key' => 'country_id', 'type' => 'select', 'label' => __('catalog.province.fields.country'), 'placeholder' => __('catalog.province.fields.country_placeholder'), 'required' => true, 'options' => Country::options()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.province.fields.status'), 'on_label' => __('catalog.province.status.active'), 'off_label' => __('catalog.province.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            'name' => AttributeValidator::requiredExistModelRelation(
                'provinces',
                'name',
                'country_id',
                $data['country_id'] ?? null,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'country_id' => config('nicename.country_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
