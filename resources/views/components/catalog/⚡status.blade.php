<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\CurrentStatusForm;
use App\Models\CurrentStatus;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Statuses master (`current_statuses` table).
 *
 * The smallest master: the table holds one column, the name. There is no
 * `is_active`, so this editor has no state switch and no Status column —
 * adding them would invent a field the database does not store.
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public CurrentStatusForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new CurrentStatus;
    }

    /**
     * Palette for the combobox, out of `CurrentStatus::COLORS` rather than a
     * list written in the Blade: the key that gets stored, the one the CSS can
     * paint and the one validation checks all have to be the SAME.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function colorOptions(): array
    {
        return collect(CurrentStatus::COLORS)
            ->map(fn(string $color): array => [
                'value' => $color,
                'label' => __('catalog.status.colors.' . $color),
            ])
            ->all();
    }
};
?>

<x-catalog.master :rows="$initialRows" :search="['name']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
    ]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.status.search_placeholder')"
            :search-label="__('catalog.status.search_label')" :singular="__('catalog.status.singular')"
            :plural="__('catalog.status.plural')" :create="__('catalog.status.create')" />

        {{-- With only two columns the COLOUR takes the slack and not the name,
        so the tag stays next to the name instead of drifting to the
        right edge and leaving a hole in the middle. --}}
        <x-catalog.table :empty="__('catalog.status.empty')" :columns="[
            ['label' => __('catalog.status.columns.name')],
            ['label' => __('catalog.status.columns.color'), 'class' => 'catalog-col-fill'],
        ]">
            <td class="catalog-cell-name" x-text="row.name"></td>
            {{-- The tag shows exactly as it will everywhere else: the colour is
            not written here, it comes from the key stored on the row. --}}
            <td class="catalog-cell-fill">
                <span class="status-tag" x-bind:class="'is-' + row.color">
                    <span class="dot"></span><span x-text="row.name"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.status.new')" :new-title="__('catalog.status.new_title')"
            :edit-title="__('catalog.status.edit_title')" :create="__('catalog.status.create')">

            {{-- Name and colour in one row: the name takes the slack. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.status.fields.name')" required name="name"
                    :placeholder="__('catalog.status.fields.name_placeholder')" alpine-error="name"
                    wire:model="form.data.name" />

                <x-inputsform.combobox span="text" :label="__('catalog.status.fields.color')" required name="color"
                    :placeholder="__('catalog.status.fields.color_placeholder')"
                    :hint="__('catalog.status.fields.color_hint')" :options="$this->colorOptions"
                    :value="$form->data?->color" alpine-error="color"
                    wire:model="form.data.color" />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
