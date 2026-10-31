<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSeasonalWindow;
use App\Actions\Catalog\UpdateSeasonalWindow;
use App\Dto\SeasonalWindowDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\SeasonalWindow;
use App\Rules\AttributeValidator;

class SeasonalWindowForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: SeasonalWindowDto::class,
            model: SeasonalWindow::class,
            create: CreateSeasonalWindow::class,
            update: UpdateSeasonalWindow::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules($excludeId);
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.seasonal_window.fields.name'), 'placeholder' => __('catalog.seasonal_window.fields.name_placeholder'), 'required' => true, 'max' => 255],
            ['key' => 'notice', 'type' => 'text', 'label' => __('catalog.seasonal_window.fields.notice'), 'placeholder' => __('catalog.seasonal_window.fields.notice_placeholder'), 'hint' => __('catalog.seasonal_window.fields.notice_hint'), 'max' => 200],
            // One control for two columns: their errors come back under each end.
            ['key' => 'range', 'type' => 'daterange', 'label' => __('catalog.seasonal_window.fields.range'), 'hint' => __('catalog.seasonal_window.fields.range_hint'), 'from_label' => __('catalog.seasonal_window.fields.starts_at'), 'to_label' => __('catalog.seasonal_window.fields.ends_at'), 'error_keys' => ['starts_at', 'ends_at']],
            ['key' => 'priority', 'type' => 'number', 'label' => __('catalog.seasonal_window.fields.priority'), 'hint' => __('catalog.seasonal_window.fields.priority_hint'), 'required' => true, 'min' => 0],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.seasonal_window.fields.status'), 'on_label' => __('catalog.seasonal_window.status.active'), 'off_label' => __('catalog.seasonal_window.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [

            'name' => AttributeValidator::uniqueIdNameLength('3', 'seasonal_windows', 'name', $excludeId),

            'starts_at' => ['required', 'date_format:Y-m-d'],

            // A season that ends before it starts is a season that never runs,
            // and nothing downstream would ever report it as a mistake.
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],

            'priority' => AttributeValidator::numericInteger(true, 0),

            // What the visitor reads on the landing: it is optional, and short on purpose.
            'notice' => ['nullable', ...AttributeValidator::stringValid(false, '3'), 'max:200'],

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
            'name' => config('nicename.name'),
            'starts_at' => __('catalog.seasonal_window.fields.starts_at'),
            'ends_at' => __('catalog.seasonal_window.fields.ends_at'),
            'priority' => __('catalog.seasonal_window.fields.priority'),
            'notice' => __('catalog.seasonal_window.fields.notice'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
