<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\CountryForm;
use App\Models\Country;
use App\Models\Currency;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Countries master (`countries` table).
 *
 * The chrome and the whole Alpine rail live in `<x-catalog.*>` and in
 * `catalogMaster()`, so only the server actions and this master's own fields
 * belong here.
 */
new class extends Component {
    use InteractsWithCatalogEditor;

    public CountryForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new Country();
    }

    /**
     * Currency options for the select, inactive ones included.
     *
     * Filtering them out would leave the select empty on a country that points
     * at a retired currency, and saving would then change its currency with
     * nobody touching it.
     *
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function currencyOptions(): array
    {
        return Currency::options();
    }
};
?>

<x-catalog.master :rows="$initialRows" :search="['code', 'name']" :rules="[
    'code' => ['required', 'alpha', ['length', 3]],
    'iso2' => ['required', 'alpha', ['length', 2]],
    'name' => ['required', ['minLength', 3], ['maxLength', 255], 'noMarkup'],
    'phone_code' => [['maxLength', 6]],
    'currency_id' => ['required'],
]">

    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar :search-placeholder="__('catalog.country.search_placeholder')" :search-label="__('catalog.country.search_label')" :singular="__('catalog.country.singular')" :plural="__('catalog.country.plural')" :create="__('catalog.country.create')" />

        <x-catalog.table :empty="__('catalog.country.empty')" :columns="[
            ['label' => __('catalog.country.columns.code')],
            ['label' => __('catalog.country.columns.iso2')],
            ['label' => __('catalog.country.columns.name'), 'class' => 'catalog-col-fill'],
            ['label' => __('catalog.country.columns.phone_code')],
            ['label' => __('catalog.country.columns.currency')],
            ['label' => __('catalog.country.columns.status')],
        ]">
            <td><span class="catalog-code" x-text="row.code"></span></td>
            <td><span class="catalog-code" x-text="row.iso2"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="catalog-cell-sym" x-text="row.phone_code"></td>
            <td class="catalog-cell-sym" x-text="row.currency"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.country.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.country.status.inactive')) }}"></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell :new="__('catalog.country.new')" :new-title="__('catalog.country.new_title')" :edit-title="__('catalog.country.edit_title')" :create="__('catalog.country.create')"
            title-key="code">

            {{-- Row 1: the two ISO identifiers and the name, which takes all the
            rest. Both codes travel together: the 3-letter one is the visible
            key, the 2-letter one feeds timezones and geo-IP. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="code" :label="__('catalog.country.fields.code')" required name="code" :hint="__('catalog.country.fields.code_hint')"
                    maxlength="3" alpine-error="code" x-mask="aaa" style="text-transform:uppercase"
                    wire:model="form.data.code" />

                <x-inputsform.input span="code" :label="__('catalog.country.fields.iso2')" required name="iso2" :hint="__('catalog.country.fields.iso2_hint')"
                    maxlength="2" alpine-error="iso2" x-mask="aa" style="text-transform:uppercase"
                    wire:model="form.data.iso2" />

                <x-inputsform.input span="text" :label="__('catalog.country.fields.name')" required name="name" :placeholder="__('catalog.country.fields.name_placeholder')"
                    alpine-error="name" wire:model="form.data.name" />
            </x-catalog.form-row>

            {{-- Row 2: the remaining fields sharing the full width. --}}
            <x-catalog.form-row>
                <x-inputsform.input span="text" :label="__('catalog.country.fields.phone_code')" name="phone_code" :hint="__('catalog.country.fields.phone_code_hint')"
                    maxlength="6" alpine-error="phone_code" wire:model="form.data.phone_code" />

                <x-inputsform.combobox span="text" :label="__('catalog.country.fields.currency')" required name="currency_id" :placeholder="__('catalog.country.fields.currency_placeholder')"
                    :options="$this->currencyOptions" :value="$form->data?->currency_id" alpine-error="currency_id"
                    wire:model="form.data.currency_id" />

                <x-inputsform.switch-field span="text" :label="__('catalog.country.fields.status')" name="is_active" :on="__('catalog.country.status.active')"
                    :off="__('catalog.country.status.inactive')" wire:model="form.data.is_active" />
            </x-catalog.form-row>

        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
