<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateRegion;
use App\Actions\Catalog\UpdateRegion;
use App\Dto\NotificationDto;
use App\Dto\RegionDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Region;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class RegionForm extends BaseForm
{
    #[Locked]
    public ?int $regionId = null;

    public ?RegionDto $regionData = null;

    public function setup(): void
    {
        $this->regionData = new RegionDto;
    }

    public function storeRegion(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateRegion::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function updateRegion(): NotificationDto
    {
        if ($this->regionId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->regionId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateRegion::class)->handle($this->regionId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadRegionData(int $id): bool
    {
        $data = $this->findRegionData($id);

        if ($data === null) {
            return false;
        }

        $this->regionId = $id;
        $this->regionData = RegionDto::fromArray($data->toArray());

        return true;
    }

    public function findRegionData(int $id): ?Region
    {
        return Region::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->regionData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin provincia reventaría en Postgres en vez de
            // marcar el campo.
            'province_id' => AttributeValidator::requireAndExists('provinces', 'id', 'province_id', true),

            // Único DENTRO de la provincia, no globalmente: el mismo nombre de
            // región se repite entre provincias distintas y las dos son válidas.
            'name' => AttributeValidator::requiredExistModelRelation(
                'regions',
                'name',
                'province_id',
                $this->regionData?->province_id,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'province_id' => config('nicename.province_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
