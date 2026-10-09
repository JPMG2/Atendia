<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\AdoptionNudgeForm;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Models\AdoptionNudge;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the adoption messages master (`adoption_nudges` table): the mail
 * that opens from a stuck account's row, one per step of the ladder.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public AdoptionNudgeForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new AdoptionNudge;
    }

    /** @return array<string, string> */
    #[Computed]
    public function stepOptions(): array
    {
        return AdoptionNudge::stepOptions();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['stepLabel', 'subject', 'body']"
    :rules="[
        'step' => ['required'],
        'subject' => ['required', ['minLength', 3], ['maxLength', 150], 'noMarkup'],
        'body' => ['required', ['minLength', 10], ['maxLength', 900], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.adoption_nudge.search_placeholder')"
            :search-label="__('catalog.adoption_nudge.search_label')"
            :singular="__('catalog.adoption_nudge.singular')"
            :plural="__('catalog.adoption_nudge.plural')"
            :create="__('catalog.adoption_nudge.create')"
        />

        <x-catalog.table
            :empty="__('catalog.adoption_nudge.empty')"
            :columns="[
                ['label' => __('catalog.adoption_nudge.columns.step')],
                ['label' => __('catalog.adoption_nudge.columns.subject')],
                ['label' => __('catalog.adoption_nudge.columns.body'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.adoption_nudge.columns.status')],
            ]"
        >
            <td class="catalog-cell-name" x-text="row.stepLabel"></td>
            <td x-text="row.subject"></td>
            <td class="catalog-cell-fill" x-text="row.body"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.adoption_nudge.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.adoption_nudge.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.adoption_nudge.new')"
            :new-title="__('catalog.adoption_nudge.new_title')"
            :edit-title="__('catalog.adoption_nudge.edit_title')"
            :create="__('catalog.adoption_nudge.create')"
        >
            {{-- Row 1: the step it is for, the subject (which takes the slack) and the state. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.adoption_nudge.fields.step')"
                    required
                    name="step"
                    :placeholder="__('catalog.adoption_nudge.fields.step_placeholder')"
                    :options="$this->stepOptions"
                    :value="$form->data?->step"
                    alpine-error="step"
                    wire:model="form.data.step"
                />

                <x-inputsform.input
                    span="long"
                    :label="__('catalog.adoption_nudge.fields.subject')"
                    required
                    name="subject"
                    :placeholder="__('catalog.adoption_nudge.fields.subject_placeholder')"
                    maxlength="150"
                    alpine-error="subject"
                    wire:model="form.data.subject"
                />

                <x-inputsform.switch-field
                    span="text"
                    :label="__('catalog.adoption_nudge.fields.status')"
                    name="is_active"
                    :on="__('catalog.adoption_nudge.status.active')"
                    :off="__('catalog.adoption_nudge.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 2: the text that lands in the mail. --}}
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    :label="__('catalog.adoption_nudge.fields.body')"
                    required
                    name="body"
                    :rows="6"
                    maxlength="900"
                    counter
                    :hint="__('catalog.adoption_nudge.fields.body_hint')"
                    alpine-error="body"
                    wire:model="form.data.body"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
