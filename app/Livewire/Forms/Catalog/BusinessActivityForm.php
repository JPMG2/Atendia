<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateBusinessActivity;
use App\Actions\Catalog\UpdateBusinessActivity;
use App\Dto\BusinessActivityDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\BusinessActivity;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class BusinessActivityForm extends BaseCatalogForm
{
    #[Locked]
    public ?int $businessActivityId = null;

    public ?BusinessActivityDto $businessActivityData = null;

    public function setup(): void
    {
        $this->businessActivityData = new BusinessActivityDto;
    }

    public function store(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateBusinessActivity::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function update(): NotificationDto
    {
        if ($this->businessActivityId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->businessActivityId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateBusinessActivity::class)->handle($this->businessActivityId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadData(int $id): bool
    {
        $data = $this->findBusinessActivityData($id);

        if ($data === null) {
            return false;
        }

        $this->businessActivityId = $id;
        $this->businessActivityData = BusinessActivityDto::fromArray($data->toArray());

        return true;
    }

    public function findBusinessActivityData(int $id): ?BusinessActivity
    {
        return BusinessActivity::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->businessActivityData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La FK es obligatoria en la tabla (`constrained()`): sin `required`
            // un alta sin rubro reventaría en Postgres en vez de marcar el campo.
            'business_sector_id' => AttributeValidator::requireAndExists('business_sectors', 'id', 'business_sector_id', true),

            // El código es único GLOBAL a propósito: es la clave con la que se va
            // a buscar el perfil del asistente para el oficio. La columna es
            // varchar(40).
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('2', 'business_activities', 'code', $excludeId),
                'max:40',
            ],

            // El nombre es único DENTRO del rubro: "Estética" puede existir en
            // Belleza y en Servicios, pero no dos veces en el mismo rubro. Un
            // unique global rechazaría la segunda; ninguno dejaría duplicar.
            'name' => AttributeValidator::requiredExistModelRelation(
                'business_activities',
                'name',
                'business_sector_id',
                $this->businessActivityData?->business_sector_id,
                $excludeId,
            ),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

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
            'business_sector_id' => config('nicename.business_sector_id'),
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
