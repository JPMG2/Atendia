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
 * El Form de CUALQUIER maestro del hub de catálogos.
 *
 * Los nueve tenían el mismo `store()`, el mismo `update()` y el mismo
 * `loadData()` copiados; lo único que cambiaba era el nombre de la propiedad
 * (`$currencyData` vs `$regionData`) y las cuatro clases a las que le hablaban.
 * Uniformar el nombre a `$data` fue lo que permitió que los cuerpos subieran
 * acá — el mismo movimiento que ya se había hecho un piso más arriba con
 * `App\Traits\InteractsWithCatalogEditor`.
 *
 * Un Form concreto queda entonces con lo que de verdad es suyo: a qué está
 * cableado (`catalog()`) y sus reglas de validación.
 */
abstract class BaseCatalogForm extends BaseForm
{
    /**
     * El registro que se está editando. `null` = alta.
     *
     * Va `#[Locked]` porque nunca lo elige el front: lo asigna `loadData()` en
     * el server a partir del id que se pidió abrir.
     */
    #[Locked]
    public ?int $recordId = null;

    /**
     * El DTO del maestro, con el estado del formulario.
     *
     * El tipo es la interfaz y no el DTO concreto porque la propiedad es una
     * sola para los nueve; PHP no deja angostar el tipo de una propiedad en la
     * hija. Livewire hidrata la clase concreta igual (ver `FormData`), y el Form
     * que necesite leer un campo suyo se lo documenta con un `@var` local.
     */
    public ?FormData $data = null;

    private ?CatalogWiring $wiring = null;

    /** A qué DTO, modelo y Actions le habla este maestro. */
    abstract protected function catalog(): CatalogWiring;

    /** Deja el DTO en blanco: es el estado con el que arranca un alta. */
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

    /** Carga el registro en el form. Devuelve false si ya no existe. */
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
