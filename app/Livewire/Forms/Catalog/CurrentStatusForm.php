<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrentStatus;
use App\Actions\Catalog\UpdateCurrentStatus;
use App\Dto\CurrentStatusDto;
use App\Models\CurrentStatus;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class CurrentStatusForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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
        return [

            'name' => AttributeValidator::uniqueIdNameLength('3', 'current_statuses', 'name', $excludeId),

            'color' => ['required', Rule::in(CurrentStatus::COLORS)],
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'color' => config('nicename.color'),
        ];
    }
}
