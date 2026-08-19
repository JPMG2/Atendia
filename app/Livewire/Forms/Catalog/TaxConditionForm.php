<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateTaxCondition;
use App\Actions\Catalog\UpdateTaxCondition;
use App\Dto\NotificationDto;
use App\Dto\TaxConditionDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\TaxCondition;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class TaxConditionForm extends BaseForm
{
    #[Locked]
    public ?int $taxConditionId = null;

    public ?TaxConditionDto $taxConditionData = null;

    public function setup(): void
    {
        $this->taxConditionData = new TaxConditionDto;
    }

    public function storeTaxCondition(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateTaxCondition::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function updateTaxCondition(): NotificationDto
    {
        if ($this->taxConditionId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->taxConditionId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateTaxCondition::class)->handle($this->taxConditionId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadTaxConditionData(int $id): bool
    {
        $data = $this->findTaxConditionData($id);

        if ($data === null) {
            return false;
        }

        $this->taxConditionId = $id;
        $this->taxConditionData = TaxConditionDto::fromArray($data->toArray());

        return true;
    }

    public function findTaxConditionData(int $id): ?TaxCondition
    {
        return TaxCondition::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->taxConditionData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $countryId = $this->taxConditionData?->country_id;

        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin país reventaría en Postgres en vez de
            // marcar el campo.
            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            // La tabla tiene UNIQUE (country_id, code) y UNIQUE (country_id, name):
            // las condiciones fiscales son de cada país, así que "RI" puede existir
            // en Argentina y en otro país a la vez. Un unique global rechazaría el
            // segundo; sin unique, el choque dentro del mismo país sería un crash
            // de Postgres atrapado por tryAction — un toast vago en vez de un
            // mensaje sobre el campo.
            'code' => AttributeValidator::requiredExistModelRelation(
                'tax_conditions',
                'code',
                'country_id',
                $countryId,
                $excludeId,
            ),

            'name' => AttributeValidator::requiredExistModelRelation(
                'tax_conditions',
                'name',
                'country_id',
                $countryId,
                $excludeId,
            ),

            'discriminate_tax' => AttributeValidator::booleanValue(true),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'country_id' => config('nicename.country_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'discriminate_tax' => config('nicename.discriminate_tax'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
