<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateAdoptionNudge;
use App\Actions\Catalog\UpdateAdoptionNudge;
use App\Dto\AdoptionNudgeDto;
use App\Models\AdoptionNudge;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class AdoptionNudgeForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: AdoptionNudgeDto::class,
            model: AdoptionNudge::class,
            create: CreateAdoptionNudge::class,
            update: UpdateAdoptionNudge::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            // One message per step: the screen asks for "the" message of a step.
            'step' => ['required', Rule::in(array_keys(AdoptionNudge::stepOptions())), Rule::unique('adoption_nudges', 'step')->ignore($excludeId)],

            'subject' => ['required', 'string', 'min:3', 'max:150'],

            'body' => ['required', 'string', 'min:10', 'max:'.AdoptionNudge::BODY_MAX],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'step' => config('nicename.step'),
            'subject' => config('nicename.subject'),
            'body' => config('nicename.body'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
