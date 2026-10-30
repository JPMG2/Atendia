<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateAdoptionNudge;
use App\Actions\Catalog\UpdateAdoptionNudge;
use App\Dto\AdoptionNudgeDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\AdoptionNudge;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class AdoptionNudgeForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        $steps = collect(AdoptionNudge::stepOptions())->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all();

        return [
            ['key' => 'step', 'type' => 'select', 'label' => __('catalog.adoption_nudge.fields.step'), 'placeholder' => __('catalog.adoption_nudge.fields.step_placeholder'), 'required' => true, 'options' => $steps],
            ['key' => 'subject', 'type' => 'text', 'label' => __('catalog.adoption_nudge.fields.subject'), 'placeholder' => __('catalog.adoption_nudge.fields.subject_placeholder'), 'required' => true, 'max' => 150],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.adoption_nudge.fields.status'), 'on_label' => __('catalog.adoption_nudge.status.active'), 'off_label' => __('catalog.adoption_nudge.status.inactive')],
            ['key' => 'body', 'type' => 'textarea', 'label' => __('catalog.adoption_nudge.fields.body'), 'hint' => __('catalog.adoption_nudge.fields.body_hint'), 'required' => true, 'max' => AdoptionNudge::BODY_MAX],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [
            // One message per step: the screen asks for "the" message of a step.
            'step' => ['required', Rule::in(array_keys(AdoptionNudge::stepOptions())), Rule::unique('adoption_nudges', 'step')->ignore($excludeId)],

            'subject' => ['required', 'string', 'min:3', 'max:150'],

            'body' => ['required', 'string', 'min:10', 'max:'.AdoptionNudge::BODY_MAX],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'step' => config('nicename.step'),
            'subject' => config('nicename.subject'),
            'body' => config('nicename.body'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
