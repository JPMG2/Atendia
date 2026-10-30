<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSupportTicketKind;
use App\Actions\Catalog\UpdateSupportTicketKind;
use App\Dto\SupportTicketKindDto;
use App\Enums\SupportTicketPriority;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\SupportTicketKind;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class SupportTicketKindForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: SupportTicketKindDto::class,
            model: SupportTicketKind::class,
            create: CreateSupportTicketKind::class,
            update: UpdateSupportTicketKind::class,
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
        $priorities = collect(SupportTicketPriority::options())->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all();

        return [
            ['key' => 'code', 'type' => 'text', 'label' => __('catalog.support_ticket_kind.fields.code'), 'hint' => __('catalog.support_ticket_kind.fields.code_hint'), 'required' => true, 'max' => 30, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.support_ticket_kind.fields.name'), 'placeholder' => __('catalog.support_ticket_kind.fields.name_placeholder'), 'required' => true, 'max' => 80],
            ['key' => 'sort_order', 'type' => 'number', 'label' => __('catalog.support_ticket_kind.fields.sort_order'), 'required' => true, 'min' => 0],
            ['key' => 'icon', 'type' => 'select', 'label' => __('catalog.support_ticket_kind.fields.icon'), 'required' => true, 'options' => SupportTicketKind::iconOptions()],
            ['key' => 'starting_priority', 'type' => 'select', 'label' => __('catalog.support_ticket_kind.fields.starting_priority'), 'hint' => __('catalog.support_ticket_kind.fields.starting_priority_hint'), 'required' => true, 'options' => $priorities],
            ['key' => 'runs_clock', 'type' => 'switch', 'label' => __('catalog.support_ticket_kind.fields.runs_clock'), 'hint' => __('catalog.support_ticket_kind.fields.runs_clock_hint'), 'on_label' => __('catalog.support_ticket_kind.clock.runs'), 'off_label' => __('catalog.support_ticket_kind.clock.none')],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.support_ticket_kind.fields.status'), 'on_label' => __('catalog.support_ticket_kind.status.active'), 'off_label' => __('catalog.support_ticket_kind.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:2',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                // Trashed ones count: a freed code would re-point old tickets.
                Rule::unique('support_ticket_kinds', 'code')->ignore($excludeId),
            ],

            'name' => ['required', ...AttributeValidator::stringValid(true, '2'), 'max:80'],

            // An icon that is not in the registry draws nothing.
            'icon' => ['required', 'string', Rule::in(SupportTicketKind::iconNames())],

            'starting_priority' => ['required', Rule::enum(SupportTicketPriority::class)],

            'runs_clock' => AttributeValidator::booleanValue(true),

            'sort_order' => AttributeValidator::numericInteger(true, 0),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'code' => __('catalog.support_ticket_kind.fields.code'),
            'name' => __('catalog.support_ticket_kind.fields.name'),
            'icon' => __('catalog.support_ticket_kind.fields.icon'),
            'starting_priority' => __('catalog.support_ticket_kind.fields.starting_priority'),
            'runs_clock' => __('catalog.support_ticket_kind.fields.runs_clock'),
            'sort_order' => __('catalog.support_ticket_kind.fields.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
