<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSupportTicketKind;
use App\Actions\Catalog\UpdateSupportTicketKind;
use App\Dto\SupportTicketKindDto;
use App\Enums\SupportTicketPriority;
use App\Models\SupportTicketKind;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class SupportTicketKindForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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

    protected function getValidationAttributes(): array
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
