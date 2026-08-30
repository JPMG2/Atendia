<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\FormData;
use App\Livewire\Forms\BaseForm;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * The Form of ANY master in the catalog hub.
 *
 * The nine carried the same `store()`, `update()` and `loadData()`; only the
 * property name and the four classes they talked to differed. Renaming it to
 * `$data` is what let the bodies move up here. A concrete Form is left with
 * what is really its own: its wiring and its validation rules.
 */
abstract class BaseCatalogForm extends BaseForm
{
    /**
     * The record being edited; `null` means a new one.
     *
     * `#[Locked]` because the front never picks it: `loadData()` assigns it on
     * the server from the id that was asked for.
     */
    #[Locked]
    public ?int $recordId = null;

    /**
     * The master's DTO, holding the form state.
     *
     * The type is the interface and not the concrete DTO because one property
     * serves the nine, and PHP does not let a child narrow it. Livewire hydrates
     * the concrete class all the same (see `FormData`).
     */
    public ?FormData $data = null;

    private ?CatalogWiring $wiring = null;

    /** Which DTO, model and Actions this master talks to. */
    abstract protected function catalog(): CatalogWiring;

    /** Blanks the DTO: the state a new record starts from. */
    public function setup(): void
    {
        $dto = $this->wiring()->dto;

        $this->data = new $dto;
    }

    public function store(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app($this->wiring()->create)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function update(): NotificationDto
    {
        if ($this->recordId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->recordId);

        return $this->tryAction(function () use ($validated) {

            $model = app($this->wiring()->update)->handle($this->recordId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    /** Loads the record into the form. False when it is gone. */
    public function loadData(int $id): bool
    {
        $record = $this->findRecord($id);

        if ($record === null) {
            return false;
        }

        $dto = $this->wiring()->dto;

        $this->recordId = $id;
        $this->data = $dto::fromArray($record->toArray());

        return true;
    }

    protected function findRecord(int $id): ?Model
    {
        $model = $this->wiring()->model;

        return $model::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->data->toPayload();
    }

    private function wiring(): CatalogWiring
    {
        return $this->wiring ??= $this->catalog();
    }
}
