<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateDemoTag;
use App\Actions\Catalog\UpdateDemoTag;
use App\Dto\DemoTagDto;
use App\Models\DemoTag;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class DemoTagForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: DemoTagDto::class,
            model: DemoTag::class,
            create: CreateDemoTag::class,
            update: UpdateDemoTag::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $isVariant = $this->data?->seasonal_window_id !== null;

        return [

            'slug' => [
                'required',
                ...AttributeValidator::stringValid(true, '2'),
                'max:40',
                // One rubro may hold one evergreen and one row per season, and
                // no more: two rows for the same pair resolve by coin flip.
                Rule::unique('demo_tags', 'slug')
                    ->where('seasonal_window_id', $this->data?->seasonal_window_id)
                    ->whereNull('deleted_at')
                    ->ignore($excludeId),
            ],

            'seasonal_window_id' => ['nullable', 'integer', Rule::exists('seasonal_windows', 'id')->whereNull('deleted_at')],

            // The evergreen is the floor of the hero and is never a hole; a
            // variant leaves blank everything it does not mean to change.
            'label' => [$isVariant ? 'nullable' : 'required', ...AttributeValidator::stringValid(false, '2'), 'max:255'],
            'business_name' => [$isVariant ? 'nullable' : 'required', ...AttributeValidator::stringValid(false, '2'), 'max:255'],
            'noun' => [$isVariant ? 'nullable' : 'required', ...AttributeValidator::stringValid(false, '2'), 'max:60'],

            // The rules see the PAYLOAD, where her two textareas have already
            // become the arrays the columns hold — not the text she typed.
            'chips' => ['nullable', 'array', 'max:6'],
            'chips.*' => ['string', ...AttributeValidator::stringValid(true, '2'), 'max:120'],

            'pool' => ['nullable', 'array', 'max:12'],
            'pool.*.side' => ['required', Rule::in(['in', 'out'])],
            'pool.*.text' => ['required', 'string', 'max:300'],

            'sort_order' => AttributeValidator::numericInteger(true, 0),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'slug' => __('catalog.demo_tag.fields.slug'),
            'seasonal_window_id' => __('catalog.demo_tag.fields.season'),
            'label' => __('catalog.demo_tag.fields.label'),
            'business_name' => __('catalog.demo_tag.fields.business_name'),
            'noun' => __('catalog.demo_tag.fields.noun'),
            'chips' => __('catalog.demo_tag.fields.chips'),
            'pool' => __('catalog.demo_tag.fields.pool'),
            'sort_order' => __('catalog.demo_tag.fields.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
