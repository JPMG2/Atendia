<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrentStatus;
use App\Actions\Catalog\UpdateCurrentStatus;
use App\Dto\CurrentStatusDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\CurrentStatus;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class CurrentStatusForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: CurrentStatusDto::class,
            model: CurrentStatus::class,
            create: CreateCurrentStatus::class,
            update: UpdateCurrentStatus::class,
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

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'name' => AttributeValidator::uniqueIdNameLength('3', 'current_statuses', 'name', $excludeId),

            'color' => ['required', Rule::in(CurrentStatus::COLORS)],
        ];
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.status.fields.name'), 'placeholder' => __('catalog.status.fields.name_placeholder'), 'required' => true],
            ['key' => 'color', 'type' => 'select', 'label' => __('catalog.status.fields.color'), 'placeholder' => __('catalog.status.fields.color_placeholder'), 'hint' => __('catalog.status.fields.color_hint'), 'required' => true, 'options' => CurrentStatus::colorOptions()],
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'name' => config('nicename.name'),
            'color' => config('nicename.color'),
        ];
    }
}
