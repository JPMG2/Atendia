<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessSector;
use App\Actions\Catalog\UpdateBusinessSector;
use App\Dto\BusinessSectorDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class BusinessSectorForm extends BaseForm
{
    #[Locked]
    public ?int $businessSectorId = null;

    public ?BusinessSectorDto $businessSectorData = null;

    public function setup(): void
    {
        $this->businessSectorData = new BusinessSectorDto;
    }

    public function storeBusinessSector(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateBusinessSector::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function updateBusinessSector(): NotificationDto
    {
        if ($this->businessSectorId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->businessSectorId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateBusinessSector::class)->handle($this->businessSectorId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadBusinessSectorData(int $id): bool
    {
        $data = $this->findBusinessSectorData($id);

        if ($data === null) {
            return false;
        }

        $this->businessSectorId = $id;
        $this->businessSectorData = BusinessSectorDto::fromArray($data->toArray());

        return true;
    }

    public function findBusinessSectorData(int $id): ?BusinessSector
    {
        return BusinessSector::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->businessSectorData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La clave del rubro es única GLOBAL en la tabla y es lo que van a
            // referenciar los perfiles del asistente: sin la regla, un código
            // repetido sale como crash de Postgres en vez de error de campo.
            // La columna es varchar(30), así que el max:255 del helper no alcanza.
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_sectors', 'code', $excludeId),
                'max:30',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('3', 'business_sectors', 'name', $excludeId),

            // La columna es nullable: sin el `nullable` un rubro sin descripción
            // rebotaría contra el min de stringValid().
            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // El tope es el de la columna (smallint). Va explícito porque sobre un
            // entero `max` compara el VALOR, no el largo.
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
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
