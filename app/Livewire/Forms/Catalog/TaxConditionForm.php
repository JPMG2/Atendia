<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateTaxCondition;
use App\Actions\Catalog\UpdateTaxCondition;
use App\Dto\TaxConditionDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\Country;
use App\Models\TaxCondition;
use App\Rules\AttributeValidator;

class TaxConditionForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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

        return self::fieldRules($excludeId, ['country_id' => $data?->country_id]);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.tax_condition.fields.code'), 'hint' => __('catalog.tax_condition.fields.code_hint'), 'required' => true, 'max' => 10, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.tax_condition.fields.name'), 'placeholder' => __('catalog.tax_condition.fields.name_placeholder'), 'required' => true],
            ['key' => 'country_id', 'type' => 'select', 'label' => __('catalog.tax_condition.fields.country'), 'placeholder' => __('catalog.tax_condition.fields.country_placeholder'), 'required' => true, 'options' => Country::options()],
            ['key' => 'discriminate_tax', 'type' => 'switch', 'label' => __('catalog.tax_condition.fields.discriminate_tax'), 'on_label' => __('catalog.tax_condition.discriminate.yes'), 'off_label' => __('catalog.tax_condition.discriminate.no')],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.tax_condition.fields.status'), 'on_label' => __('catalog.tax_condition.status.active'), 'off_label' => __('catalog.tax_condition.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        $countryId = $data['country_id'] ?? null;

        return [

            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

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

    public static function fieldNames(): array
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
