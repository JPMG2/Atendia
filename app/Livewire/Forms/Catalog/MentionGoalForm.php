<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateMentionGoal;
use App\Actions\Catalog\UpdateMentionGoal;
use App\Dto\MentionGoalDto;
use App\Models\MentionGoal;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class MentionGoalForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: MentionGoalDto::class,
            model: MentionGoal::class,
            create: CreateMentionGoal::class,
            update: UpdateMentionGoal::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            // One goal per number: two posts for the same milestone would be one too many.
            'threshold' => [
                ...AttributeValidator::numericInteger(true, 1),
                'max:1000000',
                Rule::unique('mention_goals', 'threshold')->ignore($excludeId),
            ],

            'caption' => ['required', 'string', 'min:10', 'max:'.MentionGoal::CAPTION_MAX, AttributeValidator::xssFree()],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'threshold' => config('nicename.threshold'),
            'caption' => config('nicename.caption'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
