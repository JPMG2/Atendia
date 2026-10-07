<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSeasonalWindow;
use App\Actions\Catalog\UpdateSeasonalWindow;
use App\Dto\SeasonalWindowDto;
use App\Models\SeasonalWindow;
use App\Rules\AttributeValidator;

class SeasonalWindowForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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
        return [

            'name' => AttributeValidator::uniqueIdNameLength('3', 'seasonal_windows', 'name', $excludeId),

            'starts_at' => ['required', 'date_format:Y-m-d'],

            // A season that ends before it starts is a season that never runs,
            // and nothing downstream would ever report it as a mistake.
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],

            'priority' => AttributeValidator::numericInteger(true, 0),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'starts_at' => __('catalog.seasonal_window.fields.starts_at'),
            'ends_at' => __('catalog.seasonal_window.fields.ends_at'),
            'priority' => __('catalog.seasonal_window.fields.priority'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
