<?php

use App\Actions\Catalog\RepeatSeasonalWindow;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\SeasonalWindowForm;
use App\Models\SeasonalWindow;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Component;

/**
 * Editor for the Seasons master (`seasonal_windows` table).
 *
 * A season holds only dates and a priority: what changes during it lives in
 * whatever hangs off the window, so December is described once and every
 * screen that cares reads the same answer.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public SeasonalWindowForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new SeasonalWindow;
    }

    /** Switch a season on or off from its row, with no trip to the editor. */
    public function toggleActive(int $id): void
    {
        $window = SeasonalWindow::flipActive($id);

        if ($window === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $this->dispatchNotification(new NotificationDto(
            $window->is_active
                ? __('catalog.seasonal_window.toggled_on', ['name' => $window->name])
                : __('catalog.seasonal_window.toggled_off', ['name' => $window->name]),
            NotificationType::Success,
        ));

        $this->reloadTable();
    }

    /** Clones the season and its variants a year ahead, switched off. */
    public function repeatNextYear(int $id): void
    {
        $copy = app(RepeatSeasonalWindow::class)->handle($id);

        $this->dispatchNotification($copy === null
            ? new NotificationDto(__('catalog.seasonal_window.repeat_exists'), NotificationType::Warning)
            : new NotificationDto(__('catalog.seasonal_window.repeated', ['name' => $copy->name]), NotificationType::Success));

        $this->reloadTable();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['name']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.seasonal_window.search_placeholder')"
            :search-label="__('catalog.seasonal_window.search_label')"
            :singular="__('catalog.seasonal_window.singular')"
            :plural="__('catalog.seasonal_window.plural')"
            :create="__('catalog.seasonal_window.create')"
        />

        <x-catalog.table
            :empty="__('catalog.seasonal_window.empty')"
            :columns="[
                ['label' => __('catalog.seasonal_window.columns.name'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.seasonal_window.columns.starts_at')],
                ['label' => __('catalog.seasonal_window.columns.ends_at')],
                ['label' => __('catalog.seasonal_window.columns.priority')],
                ['label' => __('catalog.seasonal_window.columns.running')],
                ['label' => __('catalog.seasonal_window.columns.status')],
                ['label' => __('catalog.seasonal_window.columns.repeat')],
            ]"
        >
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="font-mono" x-text="row.starts_at"></td>
            <td class="font-mono" x-text="row.ends_at"></td>
            <td class="font-mono" x-text="row.priority"></td>
            <td>
                {{-- Switched on and inside its dates are different things, and
                only the second one is what the visitor is seeing right now. --}}
                <span class="catalog-status" x-bind:class="row.running ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.running ? {{ \Illuminate\Support\Js::from(__('catalog.seasonal_window.status.running')) }} : {{ \Illuminate\Support\Js::from(__('catalog.seasonal_window.status.waiting')) }}"
                    ></span>
                </span>
            </td>
            {{-- The switch IS the cell: a season going wrong in front of a
            visitor gets turned off here, not three clicks away. `.stop` so it
            does not also open the editor the row click opens. --}}
            <td>
                <button
                    type="button"
                    class="catalog-status catalog-status-toggle"
                    x-bind:class="row.active ? 'is-on' : 'is-off'"
                    x-bind:aria-pressed="row.active ? 'true' : 'false'"
                    x-on:click.stop="$wire.toggleActive(row.id)"
                >
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.seasonal_window.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.seasonal_window.status.inactive')) }}"
                    ></span>
                </button>
            </td>

            <td>
                <x-ui.icon-button
                    icon="copy"
                    size="sm"
                    variant="ghost"
                    :label="__('catalog.seasonal_window.repeat')"
                    x-on:click.stop="$wire.repeatNextYear(row.id)"
                />
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.seasonal_window.new')"
            :new-title="__('catalog.seasonal_window.new_title')"
            :edit-title="__('catalog.seasonal_window.edit_title')"
            :create="__('catalog.seasonal_window.create')"
        >
            {{-- Row 1: the name takes the rest of the width. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="text"
                    :label="__('catalog.seasonal_window.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.seasonal_window.fields.name_placeholder')"
                    maxlength="255"
                    alpine-error="name"
                    wire:model="form.data.name"
                />
            </x-catalog.form-row>

            {{-- Row 2: when it runs, how it wins a tie, and whether it is on. --}}
            <x-catalog.form-row>
                {{-- The error lands on an end, never on the picker's own name:
                "desde" and "hasta" are two columns behind one control. --}}
                <x-inputsform.datepicker
                    span="text"
                    mode="range"
                    :label="__('catalog.seasonal_window.fields.range')"
                    name="range"
                    :hint="__('catalog.seasonal_window.fields.range_hint')"
                    :error="$errors->first('starts_at') ?: $errors->first('ends_at')"
                    :value="$form->data?->range"
                    wire:model="form.data.range"
                />

                <x-inputsform.input
                    span="code"
                    :label="__('catalog.seasonal_window.fields.priority')"
                    required
                    name="priority"
                    type="number"
                    min="0"
                    :hint="__('catalog.seasonal_window.fields.priority_hint')"
                    alpine-error="priority"
                    wire:model="form.data.priority"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.seasonal_window.fields.status')"
                    name="is_active"
                    :on="__('catalog.seasonal_window.status.active')"
                    :off="__('catalog.seasonal_window.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
