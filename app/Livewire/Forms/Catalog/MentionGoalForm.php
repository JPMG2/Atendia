<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateMentionGoal;
use App\Actions\Catalog\UpdateMentionGoal;
use App\Dto\MentionGoalDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\MentionGoal;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class MentionGoalForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'threshold', 'type' => 'number', 'label' => __('catalog.mention_goal.fields.threshold'), 'hint' => __('catalog.mention_goal.fields.threshold_hint'), 'required' => true, 'min' => 1, 'max' => 1000000],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.mention_goal.fields.status'), 'on_label' => __('catalog.mention_goal.status.active'), 'off_label' => __('catalog.mention_goal.status.inactive')],
            ['key' => 'caption', 'type' => 'textarea', 'label' => __('catalog.mention_goal.fields.caption'), 'hint' => __('catalog.mention_goal.fields.caption_hint'), 'required' => true, 'max' => MentionGoal::CAPTION_MAX],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
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

    public static function fieldNames(): array
    {
        return [
            'threshold' => config('nicename.threshold'),
            'caption' => config('nicename.caption'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
