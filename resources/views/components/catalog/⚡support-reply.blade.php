<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\SupportReplyForm;
use App\Models\SupportReply;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Component;

/**
 * Editor for the Support replies master (`support_replies` table): the answers
 * support gives again and again, pasted into the composer of a report.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public SupportReplyForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new SupportReply;
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['name', 'body']"
    :rules="[
        'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
        'body' => ['required', ['minLength', 10], ['maxLength', 900], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.support_reply.search_placeholder')"
            :search-label="__('catalog.support_reply.search_label')"
            :singular="__('catalog.support_reply.singular')"
            :plural="__('catalog.support_reply.plural')"
            :create="__('catalog.support_reply.create')"
        />

        <x-catalog.table
            :empty="__('catalog.support_reply.empty')"
            :columns="[
                ['label' => __('catalog.support_reply.columns.name')],
                ['label' => __('catalog.support_reply.columns.body'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.support_reply.columns.status')],
            ]"
        >
            <td class="catalog-cell-name" x-text="row.name"></td>
            <td class="catalog-cell-fill" x-text="row.body"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.support_reply.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.support_reply.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.support_reply.new')"
            :new-title="__('catalog.support_reply.new_title')"
            :edit-title="__('catalog.support_reply.edit_title')"
            :create="__('catalog.support_reply.create')"
        >
            {{-- Row 1: the name, which takes the slack, and the state. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="long"
                    :label="__('catalog.support_reply.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.support_reply.fields.name_placeholder')"
                    maxlength="255"
                    alpine-error="name"
                    wire:model="form.data.name"
                />

                <x-inputsform.switch-field
                    span="text"
                    :label="__('catalog.support_reply.fields.status')"
                    name="is_active"
                    :on="__('catalog.support_reply.status.active')"
                    :off="__('catalog.support_reply.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 2: the text that lands in the composer. --}}
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    :label="__('catalog.support_reply.fields.body')"
                    required
                    name="body"
                    :rows="5"
                    maxlength="900"
                    counter
                    :hint="__('catalog.support_reply.fields.body_hint')"
                    alpine-error="body"
                    wire:model="form.data.body"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
