<?php

declare(strict_types=1);

namespace App\Traits;

use App\Actions\Catalog\RemoveCatalogRecord;
use App\Dto\CatalogColumnDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\HasHighlights;
use App\Interfaces\Catalog\Removable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
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
     * What the delete button asks the person, decided by the server: a record
     * somebody still holds is explained instead of confirmed, and the words
     * name WHO holds it. The front only draws the dialog this describes.
     *
     * @return array{status: string, title: string, message: string, accept: string}
     */
    public function removalCheck(int $id): array
    {
        $model = $this->catalogModel();

        if (! $model instanceof Model || ! $model instanceof Removable) {
            return $this->removalAnswer(RemoveCatalogRecord::MISSING, __('catalog.remove.unsupported'));
        }

        $inspection = app(RemoveCatalogRecord::class)->inspect($model, $id);

        if ($inspection['status'] === RemoveCatalogRecord::MISSING) {
            return $this->removalAnswer(RemoveCatalogRecord::MISSING, __('catalog.remove.missing'));
        }

        $name = $this->removalName($inspection['record'], $model);

        if ($inspection['status'] === RemoveCatalogRecord::IN_USE) {
            $answer = $this->removalAnswer(
                RemoveCatalogRecord::IN_USE,
                __('catalog.remove.in_use', ['name' => $name, 'uses' => $this->removalWords($inspection['usage'])]),
                __('catalog.remove.blocked_title'),
            );

            // The way out the message suggests, one click away: only offered
            // while the record is on, since "switch it off" is the whole point.
            if ($inspection['record']?->getAttribute('is_active') === true) {
                $answer['deactivate'] = __('catalog.remove.deactivate');
                $answer['dismiss'] = __('catalog.remove.understood');
            }

            return $answer;
        }

        $entity = app(NotificationService::class)->entityName($model);
        $message = __('catalog.remove.confirm', ['name' => $name]);

        if ($inspection['owns'] !== []) {
            $message .= ' '.__('catalog.remove.confirm_owns', ['owns' => $this->removalWords($inspection['owns'])]);
        }

        return $this->removalAnswer(
            RemoveCatalogRecord::REMOVED,
            $message,
            __('catalog.remove.confirm_title', ['entity' => mb_strtolower($entity)]),
            __('catalog.remove.accept', ['entity' => mb_strtolower($entity)]),
        );
    }

    /** Deletes after the person confirmed. Returns whether the record is gone, so the front can leave the form. */
    public function removeRecord(int $id): bool
    {
        $model = $this->catalogModel();

        if (! $model instanceof Model || ! $model instanceof Removable) {
            $this->dispatchNotification(new NotificationDto(__('catalog.remove.unsupported'), NotificationType::Error));

            return false;
        }

        $result = app(RemoveCatalogRecord::class)->handle($model, $id);

        $this->dispatchNotification(match ($result) {
            RemoveCatalogRecord::REMOVED => app(NotificationService::class)->deleted($model),
            RemoveCatalogRecord::IN_USE => new NotificationDto(__('catalog.remove.blocked_title'), NotificationType::Warning),
            default => new NotificationDto(__('notifications.not_found'), NotificationType::Error),
        });

        if ($result === RemoveCatalogRecord::REMOVED) {
            $this->resetForm();

            $this->reloadTable();
        }

        return $result === RemoveCatalogRecord::REMOVED;
    }

    /**
     * Switches a held record off from the "in use" dialog. Returns whether it
     * did, so the front can go back to a list that already shows it inactive.
     */
    public function deactivateRecord(int $id): bool
    {
        $model = $this->catalogModel();

        if (! $model instanceof Model || ! $model instanceof Removable) {
            return false;
        }

        $record = $model->newQuery()->find($id);

        if ($record === null || $record->getAttribute('is_active') !== true) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return false;
        }

        $record->forceFill(['is_active' => false])->save();

        $this->dispatchNotification(new NotificationDto(
            __('catalog.remove.deactivated', ['name' => $this->removalName($record, $model)]),
            NotificationType::Success,
        ));

        $this->resetForm();

        $this->reloadTable();

        return true;
    }

    /** @return array{status: string, title: string, message: string, accept: string, deactivate?: string, dismiss?: string} */
    private function removalAnswer(string $status, string $message, string $title = '', string $accept = ''): array
    {
        return ['status' => $status, 'title' => $title, 'message' => $message, 'accept' => $accept];
    }

    /** The name the dialog quotes: whatever identifies the row to a person. */
    private function removalName(?Model $record, Model $fallback): string
    {
        foreach (['name', 'label', 'subject', 'code', 'step'] as $column) {
            $value = $record?->getAttribute($column);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return app(NotificationService::class)->entityName($fallback);
    }

    /**
     * "14 negocios y 3 países", from `usage` counts keyed by their noun.
     *
     * @param  array<string, int>  $counts
     */
    private function removalWords(array $counts): string
    {
        return collect($counts)
            ->map(fn (int $count, string $noun): string => trans_choice('catalog.usage.'.$noun, $count, ['count' => $count]))
            ->values()
            ->join(', ', __('catalog.remove.and'));
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
     * The table header, from the model when it says its own columns: the mobile
     * API reads the same list. An editor still on a hand-written header gets none.
     *
     * @return list<array{label: string, class?: string}>
     */
    #[Computed]
    public function tableColumns(): array
    {
        $model = $this->catalogModel();

        return $model instanceof HasColumns
            ? array_map(fn (CatalogColumnDto $column): array => $column->toTableColumn(), $model->catalogColumns())
            : [];
    }

    /**
     * The row keys the search box looks through, from the same columns the table
     * shows. Empty for an editor that still writes its own list.
     *
     * @return list<string>
     */
    #[Computed]
    public function searchKeys(): array
    {
        $model = $this->catalogModel();

        return $model instanceof HasColumns ? CatalogColumnDto::searchKeys($model->catalogColumns()) : [];
    }

    /** The row key the chips over the list filter by; null when the master offers none. */
    #[Computed]
    public function chipKey(): ?string
    {
        $model = $this->catalogModel();

        return $model instanceof HasColumns ? CatalogColumnDto::chipKey($model->catalogColumns()) : null;
    }

    /**
     * The values of the chip column in the server's Spanish order: the country selector's
     * options when there are too many for chips. Empty when the master offers none.
     *
     * @return list<string>
     */
    #[Computed]
    public function chipOptions(): array
    {
        return $this->chipKey === null ? [] : CatalogColumnDto::chipValues($this->rows, $this->chipKey);
    }

    /**
     * What the master tells before its list, from the model, as the mobile API says it.
     *
     * @return list<array{when: string, text: string}>
     */
    #[Computed]
    public function highlights(): array
    {
        $model = $this->catalogModel();

        return $model instanceof HasHighlights ? $model->catalogHighlights() : [];
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
