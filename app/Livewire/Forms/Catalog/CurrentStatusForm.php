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

            // `name` is UNIQUE and the master's only real data: without the rule a
            // repeated status is a database crash caught by tryAction, not a field
            // error.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'current_statuses', 'name', $excludeId),

            // What is stored is a token KEY, not a hex. Validating against the
            // palette is what stops a value the CSS cannot paint from leaving the
            // tag transparent with no warning.
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
