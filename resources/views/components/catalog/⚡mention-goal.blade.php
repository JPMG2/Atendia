<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\MentionGoalForm;
use App\Models\MentionGoal;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Component;

/**
 * Editor for the mention goals master (`mention_goals` table): how many
 * conversations in a month earn a business a mention, and the post's words.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public MentionGoalForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new MentionGoal;
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="$this->searchKeys"
    :rules="[
        'threshold' => ['required', 'integer', ['min', 1], ['max', 1000000]],
        'caption' => ['required', ['minLength', 10], ['maxLength', 280], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.mention_goal.search_placeholder')"
            :search-label="__('catalog.mention_goal.search_label')"
            :singular="__('catalog.mention_goal.singular')"
            :plural="__('catalog.mention_goal.plural')"
            :create="__('catalog.mention_goal.create')"
        />

        <x-catalog.table
            :empty="__('catalog.mention_goal.empty')"
            :columns="$this->tableColumns"
        >
            <td class="catalog-cell-name" x-text="row.achievement"></td>
            <td class="catalog-cell-fill" x-text="row.caption"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.mention_goal.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.mention_goal.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.mention_goal.new')"
            :new-title="__('catalog.mention_goal.new_title')"
            :edit-title="__('catalog.mention_goal.edit_title')"
            :create="__('catalog.mention_goal.create')"
        >
            {{-- Row 1: the number that earns the mention and the state. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="text"
                    :label="__('catalog.mention_goal.fields.threshold')"
                    required
                    name="threshold"
                    type="number"
                    min="1"
                    :hint="__('catalog.mention_goal.fields.threshold_hint')"
                    alpine-error="threshold"
                    wire:model="form.data.threshold"
                />

                <x-inputsform.switch-field
                    span="text"
                    :label="__('catalog.mention_goal.fields.status')"
                    name="is_active"
                    :on="__('catalog.mention_goal.status.active')"
                    :off="__('catalog.mention_goal.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 2: the post. --}}
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    :label="__('catalog.mention_goal.fields.caption')"
                    required
                    name="caption"
                    :rows="4"
                    maxlength="280"
                    counter
                    :hint="__('catalog.mention_goal.fields.caption_hint')"
                    alpine-error="caption"
                    wire:model="form.data.caption"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
