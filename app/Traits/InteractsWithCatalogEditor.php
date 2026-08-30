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
 * The server actions of a catalog editor, written once.
 *
 * The 9 editors did the same thing — save, notify, clear the form, refresh the
 * table — and only the Form's method name differed. `BaseCatalogForm` made that
 * name the same everywhere, so the whole block could move here. An editor now
 * declares its Form and its model; the rest are its fields in Blade.
 */
trait InteractsWithCatalogEditor
{
    use HasNotifications;

    /**
     * Seed of the Alpine rail, FROZEN at mount. See `<x-catalog.master>`: were it
     * to change, Alpine would re-initialise the editor.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $initialRows = [];

    /** The master's Form. Each editor declares it with its concrete type. */
    abstract protected function catalogForm(): BaseCatalogForm;

    /** The master's model, which knows how to describe its own rows. */
    abstract protected function catalogModel(): DataTable;

    public function mount(): void
    {
        $this->catalogForm()->setup();

        $this->initialRows = $this->rows->all();
    }

    /**
     * Returns whether it saved, so Alpine knows whether to go back to the list or
     * leave the person in the form with what they typed.
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
     * Returns whether it could open. A record that is gone gets a notice and the
     * front stays on the list, instead of an empty form or a raw 404.
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
     * A new record starts blank: clearing Alpine's state is not enough, the
     * server form still holds the record opened before.
     */
    public function openCreate(): void
    {
        $this->resetForm();
    }

    /**
     * Rows for the Alpine rail, handed over once at mount: the search box and the
     * counter filter client-side, with no request. The model decides the shape of
     * a row, not the editor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return $this->catalogModel()->catalogRows();
    }

    /**
     * The single place deciding what happens after a write. The gate goes here
     * the day authorisation stops living only in the admin panel middleware —
     * once, not nine times.
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
