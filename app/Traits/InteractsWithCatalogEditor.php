<?php

declare(strict_types=1);

namespace App\Traits;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Las acciones de server de un editor de catálogo, UNA sola vez.
 *
 * Los 9 editores hacían exactamente lo mismo —guardar, avisar, limpiar el form y
 * refrescar la tabla—; lo único que cambiaba era el nombre del método del Form
 * (`storeCurrency` vs `storeBusinessActivity`). Con `BaseCatalogForm` ese nombre
 * pasó a ser el mismo en todos, así que el bloque entero pudo mudarse acá.
 *
 * El editor solo declara su Form y su modelo; lo demás son sus campos en Blade.
 */
trait InteractsWithCatalogEditor
{
    use HasNotifications;

    /**
     * Semilla del riel de Alpine, CONGELADA al montar. Ver el comentario de
     * `<x-catalog.master>`: si cambiara, Alpine re-inicializaría el editor.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $initialRows = [];

    /** El Form del maestro. Cada editor lo declara con su tipo concreto. */
    abstract protected function catalogForm(): BaseCatalogForm;

    /** El modelo del maestro, que sabe describir sus propias filas. */
    abstract protected function catalogModel(): DataTable;

    public function mount(): void
    {
        $this->catalogForm()->setup();

        $this->initialRows = $this->rows->all();
    }

    /**
     * Devuelve si se guardó, para que Alpine sepa si volver a la lista o dejar
     * al usuario en el formulario con lo que escribió.
     */
    public function create(): bool
    {
        return $this->persist($this->catalogForm()->store());
    }

    public function update(): bool
    {
        return $this->persist($this->catalogForm()->update());
    }

    /**
     * Devuelve si se pudo abrir. Si el registro ya no existe avisa y el front se
     * queda en la lista, en vez de mostrar un formulario vacío o un 404 crudo.
     */
    public function openEdit(int $id): bool
    {
        if (! $this->catalogForm()->loadData($id)) {
            $this->dispatchNotification(
                new NotificationDto(__('notifications.not_found'), NotificationType::Error),
            );

            return false;
        }

        return true;
    }

    /**
     * Un alta arranca en blanco: vaciar el estado de Alpine no alcanza, el form
     * del server sigue con el registro que se abrió antes.
     */
    public function openCreate(): void
    {
        $this->resetForm();
    }

    /**
     * Filas del maestro para el riel de Alpine. Se entregan una sola vez al
     * montar: el buscador y el contador filtran client-side, sin request al
     * server. La forma de cada fila la decide el modelo, no el editor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return $this->catalogModel()->catalogRows();
    }

    /**
     * El único lugar donde se decide qué pasa después de escribir. Acá va la
     * gate el día que la autorización deje de estar solo en el middleware del
     * panel admin — una vez, no nueve. Ver la memoria del pendiente.
     */
    private function persist(NotificationDto $notification): bool
    {
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return false;
        }

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    protected function resetForm(): void
    {
        $this->catalogForm()->reset();

        $this->catalogForm()->setup();
    }

    protected function reloadTable(): void
    {
        unset($this->rows);

        $this->dispatch('catalog-rows-refreshed', rows: $this->rows);
    }
}
