<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrentStatus;
use App\Actions\Catalog\UpdateCurrentStatus;
use App\Dto\CurrentStatusDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\CurrentStatus;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class CurrentStatusForm extends BaseForm
{
    #[Locked]
    public ?int $currentStatusId = null;

    public ?CurrentStatusDto $currentStatusData = null;

    public function setup(): void
    {
        $this->currentStatusData = new CurrentStatusDto;
    }

    public function storeCurrentStatus(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateCurrentStatus::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function updateCurrentStatus(): NotificationDto
    {
        if ($this->currentStatusId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->currentStatusId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateCurrentStatus::class)->handle($this->currentStatusId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadCurrentStatusData(int $id): bool
    {
        $data = $this->findCurrentStatusData($id);

        if ($data === null) {
            return false;
        }

        $this->currentStatusId = $id;
        $this->currentStatusData = CurrentStatusDto::fromArray($data->toArray());

        return true;
    }

    public function findCurrentStatusData(int $id): ?CurrentStatus
    {
        return CurrentStatus::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->currentStatusData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // `name` es UNIQUE en la tabla y es el ÚNICO dato del maestro: sin la
            // regla, un estado repetido no sería un error de campo sino un crash
            // de base atrapado por tryAction.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'current_statuses', 'name', $excludeId),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
        ];
    }
}
