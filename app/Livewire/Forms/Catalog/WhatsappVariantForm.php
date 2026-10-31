<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateWhatsappVariant;
use App\Actions\Catalog\UpdateWhatsappVariant;
use App\Classes\Main\OpeningHours;
use App\Dto\WhatsappVariantDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\BusinessHour;
use App\Models\SeasonalWindow;
use App\Models\WhatsappVariant;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class WhatsappVariantForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    public static function phoneFields(): array
    {
        $names = BusinessHour::dayNames();

        return [
            ['key' => 'seasonal_window_id', 'type' => 'select', 'label' => __('catalog.whatsapp_variant.fields.season'), 'placeholder' => __('catalog.whatsapp_variant.fields.season_placeholder'), 'hint' => __('catalog.whatsapp_variant.fields.season_hint'), 'required' => true, 'options' => SeasonalWindow::options()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.whatsapp_variant.fields.status'), 'on_label' => __('catalog.whatsapp_variant.status.active'), 'off_label' => __('catalog.whatsapp_variant.status.inactive')],
            ['key' => 'phone', 'type' => 'text', 'label' => __('catalog.whatsapp_variant.fields.phone'), 'placeholder' => __('catalog.whatsapp_variant.fields.phone_placeholder'), 'hint' => __('catalog.whatsapp_variant.fields.phone_hint'), 'max' => 30, 'mono' => true],
            ['key' => 'override_schedule', 'type' => 'switch', 'label' => __('catalog.whatsapp_variant.fields.override_schedule'), 'hint' => __('catalog.whatsapp_variant.fields.override_schedule_hint'), 'on_label' => __('catalog.whatsapp_variant.status.own_week'), 'off_label' => __('catalog.whatsapp_variant.status.inherits')],
            [
                'key' => 'opening_schedule',
                'type' => 'week',
                'label' => __('catalog.whatsapp_variant.fields.opening_schedule'),
                'hint' => __('catalog.whatsapp_variant.fields.opening_schedule_hint'),
                // Like the web, the grid only exists while the season brings its own week.
                'show_when' => ['key' => 'override_schedule', 'value' => true],
                'days' => array_map(fn (int $day): array => ['day' => $day, 'label' => $names[$day]], OpeningHours::DAY_ORDER),
                // The web offers Monday onto the weekdays here, not onto Saturday: that button is the company's.
                'words' => [
                    'closed' => __('company.hours.closed'),
                    'opens' => __('company.hours.opens'),
                    'closes' => __('company.hours.closes'),
                    'apply_weekdays' => __('company.hours.apply_weekdays'),
                    'placeholder' => __('company.hours.time_placeholder'),
                    'overnight' => __('company.hours.overnight_hint'),
                ],
            ],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
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
            'seasonal_window_id' => __('catalog.whatsapp_variant.fields.season'),
            'phone' => __('catalog.whatsapp_variant.fields.phone'),
            'opening_schedule' => __('catalog.whatsapp_variant.fields.opening_schedule'),
            'opening_schedule.*.0' => __('company.hours.opens'),
            'opening_schedule.*.1' => __('company.hours.closes'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
