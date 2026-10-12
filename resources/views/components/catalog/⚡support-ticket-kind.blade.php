<?php

use App\Enums\SupportTicketPriority;
use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\SupportTicketKindForm;
use App\Models\SupportTicketKind;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the support report kinds master (`support_ticket_kinds` table).
 *
 * What a business can file from the widget. The code is the key every ticket
 * holds, so it is chosen once; the name, the glyph, where a report starts and
 * whether it runs a clock are hers to change.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public SupportTicketKindForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new SupportTicketKind;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function priorityOptions(): array
    {
        return collect(SupportTicketPriority::options())
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function iconOptions(): array
    {
        return SupportTicketKind::iconOptions();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['code', 'name']"
    :rules="[
        'code' => ['required', ['minLength', 2], ['maxLength', 30], 'noMarkup'],
        'name' => ['required', ['minLength', 2], ['maxLength', 80], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.support_ticket_kind.search_placeholder')"
            :search-label="__('catalog.support_ticket_kind.search_label')"
            :singular="__('catalog.support_ticket_kind.singular')"
            :plural="__('catalog.support_ticket_kind.plural')"
            :create="__('catalog.support_ticket_kind.create')"
        />

        <x-catalog.table
            :empty="__('catalog.support_ticket_kind.empty')"
            :columns="[
                ['label' => __('catalog.support_ticket_kind.columns.code')],
                ['label' => __('catalog.support_ticket_kind.columns.name'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.support_ticket_kind.columns.priority')],
                ['label' => __('catalog.support_ticket_kind.columns.clock')],
                ['label' => __('catalog.support_ticket_kind.columns.sort_order')],
                ['label' => __('catalog.support_ticket_kind.columns.status')],
            ]"
        >
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="catalog-cell-sym" x-text="row.priority"></td>
            <td
                class="catalog-cell-sym"
                x-text="row.runs_clock ? {{ \Illuminate\Support\Js::from(__('catalog.support_ticket_kind.clock.runs')) }} : {{ \Illuminate\Support\Js::from(__('catalog.support_ticket_kind.clock.none')) }}"
            ></td>
            <td class="font-mono" x-text="row.sort_order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.support_ticket_kind.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.support_ticket_kind.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.support_ticket_kind.new')"
            :new-title="__('catalog.support_ticket_kind.new_title')"
            :edit-title="__('catalog.support_ticket_kind.edit_title')"
            :create="__('catalog.support_ticket_kind.create')"
        >
            {{-- Row 1: the key, the name the business reads, and where it sits. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="code"
                    :label="__('catalog.support_ticket_kind.fields.code')"
                    required
                    name="code"
                    :hint="__('catalog.support_ticket_kind.fields.code_hint')"
                    maxlength="30"
                    alpine-error="code"
                    :disabled="$form->recordId !== null"
                    wire:model="form.data.code"
                />

                <x-inputsform.input
                    span="text"
                    :label="__('catalog.support_ticket_kind.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.support_ticket_kind.fields.name_placeholder')"
                    maxlength="80"
                    alpine-error="name"
                    wire:model="form.data.name"
                />

                <x-inputsform.input
                    span="code"
                    :label="__('catalog.support_ticket_kind.fields.sort_order')"
                    required
                    name="sort_order"
                    type="number"
                    min="0"
                    alpine-error="sort_order"
                    wire:model="form.data.sort_order"
                />
            </x-catalog.form-row>

            {{-- Row 2: how it looks, where it starts, whether it presses, and whether it is on. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="short"
                    :label="__('catalog.support_ticket_kind.fields.icon')"
                    required
                    name="icon"
                    :options="$this->iconOptions"
                    :value="$form->data?->icon"
                    wire:model="form.data.icon"
                />

                <x-inputsform.combobox
                    span="short"
                    :label="__('catalog.support_ticket_kind.fields.starting_priority')"
                    required
                    name="starting_priority"
                    :hint="__('catalog.support_ticket_kind.fields.starting_priority_hint')"
                    :options="$this->priorityOptions"
                    :value="$form->data?->starting_priority"
                    wire:model="form.data.starting_priority"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.support_ticket_kind.fields.runs_clock')"
                    name="runs_clock"
                    :hint="__('catalog.support_ticket_kind.fields.runs_clock_hint')"
                    :on="__('catalog.support_ticket_kind.clock.runs')"
                    :off="__('catalog.support_ticket_kind.clock.none')"
                    wire:model="form.data.runs_clock"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.support_ticket_kind.fields.status')"
                    name="is_active"
                    :on="__('catalog.support_ticket_kind.status.active')"
                    :off="__('catalog.support_ticket_kind.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
