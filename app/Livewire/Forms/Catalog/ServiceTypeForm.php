<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceType;
use App\Actions\Catalog\UpdateServiceType;
use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Dto\ServiceTypeDto;
use App\Enums\NotificationType;
use App\Models\ServiceAttribute;
use App\Models\ServiceType;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceTypeForm extends BaseCatalogForm
{
    /**
     * The attribute set of this type (Magento's pattern): one row per
     * assigned attribute, its position IS the pivot sort_order. What is
     * required/labelled here belongs to THIS instance, never the attribute.
     *
     * @var list<array{service_attribute_id: int|string|null, is_required: bool, label_override: ?string, hint_override: ?string}>
     */
    public array $attributeRows = [];

    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ServiceTypeDto::class,
            model: ServiceType::class,
            create: CreateServiceType::class,
            update: UpdateServiceType::class,
        );
    }

    public function setup(): void
    {
        parent::setup();

        $this->attributeRows = [];
    }

    public function loadData(int $id): bool
    {
        if (! parent::loadData($id)) {
            return false;
        }

        $this->attributeRows = $this->findRecord($id)->serviceAttributes
            ->map(fn (ServiceAttribute $attribute): array => [
                'service_attribute_id' => $attribute->id,
                'is_required' => (bool) $attribute->pivot->is_required,
                'label_override' => $attribute->pivot->label_override,
                'hint_override' => $attribute->pivot->hint_override,
            ])
            ->values()
            ->all();

        return true;
    }

    public function addAttributeRow(): void
    {
        $this->attributeRows[] = [
            'service_attribute_id' => null,
            'is_required' => false,
            'label_override' => null,
            'hint_override' => null,
        ];
    }

    /** Removing only clears the screen: the pivot reconciles on save. */
    public function removeAttributeRow(int $index): void
    {
        unset($this->attributeRows[$index]);

        $this->attributeRows = array_values($this->attributeRows);
    }

    /** wire:sort drops a dragged row at its new position; save persists it. */
    public function moveAttributeRow(int $from, int $to): void
    {
        if (! array_key_exists($from, $this->attributeRows)) {
            return;
        }

        $row = $this->attributeRows[$from];
        unset($this->attributeRows[$from]);

        $rows = array_values($this->attributeRows);
        array_splice($rows, max(0, $to), 0, [$row]);

        $this->attributeRows = $rows;
    }

    /**
     * "Aplicar a todo el rubro": suggests the type to every activity of its
     * sector. Only an EXISTING type can reach out — the button hides on a
     * new one, and this guard backs the hiding.
     */
    public function applyToSector(): NotificationDto
    {
        if ($this->recordId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $type = $this->findRecord($this->recordId);

        if (! $type instanceof ServiceType) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        if ($type->business_sector_id === null) {
            return new NotificationDto(__('catalog.service_type.suggest.needs_sector'), NotificationType::Warning);
        }

        $added = $type->suggestToSector();

        return $added === 0
            ? new NotificationDto(__('catalog.service_type.suggest.none'), NotificationType::Info)
            : new NotificationDto(trans_choice('catalog.service_type.suggest.done', $added, ['count' => $added]), NotificationType::Success);
    }

    public function store(): NotificationDto
    {
        $this->validateAttributeRows();

        $notification = parent::store();
        $this->syncAttributeRows($notification);

        return $notification;
    }

    public function update(): NotificationDto
    {
        $this->validateAttributeRows();

        $notification = parent::update();
        $this->syncAttributeRows($notification);

        return $notification;
    }

    /**
     * Rows validate OUTSIDE the DTO payload on purpose: the create/update
     * Actions fill the model, and a pivot key in their data would be
     * discarded attributes under strict models.
     */
    private function validateAttributeRows(): void
    {
        Validator::make(
            ['attribute_rows' => $this->attributeRows],
            [
                'attribute_rows' => ['array'],
                'attribute_rows.*.service_attribute_id' => ['nullable', 'integer', Rule::exists('service_attributes', 'id')],
                'attribute_rows.*.label_override' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
                'attribute_rows.*.hint_override' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
            ],
            [],
            [
                'attribute_rows.*.service_attribute_id' => __('catalog.service_type.attributes.field'),
                'attribute_rows.*.label_override' => __('catalog.service_type.attributes.label'),
                'attribute_rows.*.hint_override' => __('catalog.service_type.attributes.hint_field'),
            ],
        )->validate();
    }

    /**
     * What left the screen leaves the pivot (reconcile-on-save, the social
     * rows contract). A rowless attribute or a repeated pick is skipped, so
     * a half-filled row never blocks the save of the type itself.
     */
    private function syncAttributeRows(NotificationDto $notification): void
    {
        if ($notification->type === NotificationType::Error || $this->savedId === null) {
            return;
        }

        $type = $this->findRecord($this->savedId);

        if (! $type instanceof ServiceType) {
            return;
        }

        $sync = [];

        foreach (array_values($this->attributeRows) as $position => $row) {
            $id = DtoCast::toNullableId($row['service_attribute_id'] ?? null);

            if ($id === null || isset($sync[$id])) {
                continue;
            }

            $sync[$id] = [
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'sort_order' => $position,
                'label_override' => DtoCast::squish($row['label_override'] ?? null),
                'hint_override' => DtoCast::squish($row['hint_override'] ?? null),
            ];
        }

        $type->serviceAttributes()->sync($sync);
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_types', 'code', $excludeId),
                'max:40',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'service_types', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            'service_modality_id' => AttributeValidator::requireAndExists(
                'service_modalities', 'id', 'service_modality_id', true,
            ),

            'business_sector_id' => AttributeValidator::requireAndExists(
                'business_sectors', 'id', 'business_sector_id', false,
            ),

            'sort_order' => [
                ...AttributeValidator::numericInteger(true, 0),
                'max:32767',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'service_modality_id' => config('nicename.service_modality_id'),
            'business_sector_id' => config('nicename.business_sector_id'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
