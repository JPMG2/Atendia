<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateWhatsappVariant;
use App\Actions\Catalog\UpdateWhatsappVariant;
use App\Dto\WhatsappVariantDto;
use App\Models\WhatsappVariant;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class WhatsappVariantForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: WhatsappVariantDto::class,
            model: WhatsappVariant::class,
            create: CreateWhatsappVariant::class,
            update: UpdateWhatsappVariant::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            // One season speaks once: two live rows for it would resolve by coin flip.
            'seasonal_window_id' => [
                'required',
                'integer',
                Rule::exists('seasonal_windows', 'id')->whereNull('deleted_at'),
                Rule::unique('whatsapp_variants', 'seasonal_window_id')
                    ->whereNull('deleted_at')
                    ->ignore($excludeId),
            ],

            // Everything below is optional: a variant fills what it changes and
            // inherits the rest from the company row.
            'phone' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],
            ...AttributeValidator::weekSchedule('opening_schedule'),
            'whatsapp_greeting' => ['nullable', ...AttributeValidator::stringValid(false, '3'), 'max:300'],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'seasonal_window_id' => __('catalog.whatsapp_variant.fields.season'),
            'phone' => __('catalog.whatsapp_variant.fields.phone'),
            'opening_schedule' => __('catalog.whatsapp_variant.fields.opening_schedule'),
            'opening_schedule.*.0' => __('company.hours.opens'),
            'opening_schedule.*.1' => __('company.hours.closes'),
            'whatsapp_greeting' => __('catalog.whatsapp_variant.fields.whatsapp_greeting'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
