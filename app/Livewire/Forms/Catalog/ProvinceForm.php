<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateProvince;
use App\Actions\Catalog\UpdateProvince;
use App\Dto\NotificationDto;
use App\Dto\ProvinceDto;
use App\Enums\NotificationType;
use App\Models\Province;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class ProvinceForm extends BaseCatalogForm
{
    #[Locked]
    public ?int $provinceId = null;

    public ?ProvinceDto $provinceData = null;

    public function setup(): void
    {
        $this->provinceData = new ProvinceDto;
    }

    public function store(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateProvince::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function update(): NotificationDto
    {
        if ($this->provinceId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->provinceId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateProvince::class)->handle($this->provinceId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadData(int $id): bool
    {
        $data = $this->findProvinceData($id);

        if ($data === null) {
            return false;
        }

        $this->provinceId = $id;
        $this->provinceData = ProvinceDto::fromArray($data->toArray());

        return true;
    }

    public function findProvinceData(int $id): ?Province
    {
        return Province::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->provinceData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin país reventaría en Postgres en vez de
            // marcar el campo.
            'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

            // El nombre es único DENTRO del país: "Córdoba" existe en Argentina
            // y en España, y las dos son válidas. Un unique global rechazaría la
            // segunda, y uno inexistente dejaría cargar Córdoba dos veces en el
            // mismo país. Por eso el unique va scopeado por country_id.
            'name' => AttributeValidator::requiredExistModelRelation(
                'provinces',
                'name',
                'country_id',
                $this->provinceData?->country_id,
                $excludeId,
            ),

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'country_id' => config('nicename.country_id'),
            'name' => config('nicename.name'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
