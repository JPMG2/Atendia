<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\CountryHolidayForm;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Traits\InteractsWithCatalogEditor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the Holidays master (`country_holidays` table): the national days
 * a business loads into its calendar. Three shapes — every year, from Easter,
 * or the exact date of one year — which is what lets a decree be typed in.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public CountryHolidayForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new CountryHoliday;
    }

    /** Every country, active or not: a row of an inactive one must still read its name when edited. */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::options(label: fn (Country $country): string => $country->name);
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function kindOptions(): array
    {
        return collect([CountryHoliday::KIND_FIXED, CountryHoliday::KIND_EASTER, CountryHoliday::KIND_ONCE])
            ->map(fn (string $kind): array => ['value' => $kind, 'label' => __('catalog.country_holiday.kinds.'.$kind)])
            ->all();
    }

    /** @return list<array{value: int, label: string}> */
    #[Computed]
    public function monthOptions(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): array => ['value' => $month, 'label' => __('catalog.country_holiday.months.'.$month)])
            ->all();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['country', 'name', 'when']"
    :rules="[
        'country_id' => ['required'],
        'name' => ['required', ['minLength', 3], ['maxLength', 80], 'noMarkup'],
    ]"
>
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.country_holiday.search_placeholder')"
            :search-label="__('catalog.country_holiday.search_label')"
            :singular="__('catalog.country_holiday.singular')"
            :plural="__('catalog.country_holiday.plural')"
            :create="__('catalog.country_holiday.create')"
        />

        <x-catalog.table
            :empty="__('catalog.country_holiday.empty')"
            :columns="[
                ['label' => __('catalog.country_holiday.columns.country')],
                ['label' => __('catalog.country_holiday.columns.name'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.country_holiday.columns.when')],
                ['label' => __('catalog.country_holiday.columns.kind')],
                ['label' => __('catalog.country_holiday.columns.status')],
            ]"
        >
            <td x-text="row.country"></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="font-mono" x-text="row.when"></td>
            <td>
                <span class="status-tag is-neutral" x-show="row.kind === 'fixed'">{{ __('catalog.country_holiday.kinds.fixed') }}</span>
                <span class="status-tag is-info" x-show="row.kind === 'easter'">{{ __('catalog.country_holiday.kinds.easter') }}</span>
                <span class="status-tag is-brand" x-show="row.kind === 'once'">{{ __('catalog.country_holiday.kinds.once') }}</span>
            </td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.country_holiday.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.country_holiday.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.country_holiday.new')"
            :new-title="__('catalog.country_holiday.new_title')"
            :edit-title="__('catalog.country_holiday.edit_title')"
            :create="__('catalog.country_holiday.create')"
            :deletable="false"
        >
            {{-- Row 1: where it counts and what it is called, which takes the rest. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.country_holiday.fields.country_id')"
                    required
                    name="country_id"
                    :placeholder="__('catalog.country_holiday.fields.country_placeholder')"
                    :options="$this->countryOptions"
                    :value="$form->data?->country_id"
                    alpine-error="country_id"
                    wire:model="form.data.country_id"
                />

                <x-inputsform.input
                    span="long"
                    :label="__('catalog.country_holiday.fields.name')"
                    required
                    name="name"
                    :placeholder="__('catalog.country_holiday.fields.name_placeholder')"
                    alpine-error="name"
                    wire:model="form.data.name"
                />
            </x-catalog.form-row>

            {{-- Row 2: the shape of the date, and whether it counts. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.country_holiday.fields.kind')"
                    required
                    name="kind"
                    :hint="__('catalog.country_holiday.fields.kind_hint')"
                    :options="$this->kindOptions"
                    :value="$form->data?->kind"
                    alpine-error="kind"
                    wire:model="form.data.kind"
                />

                <x-inputsform.switch-field
                    span="text"
                    :label="__('catalog.country_holiday.fields.is_active')"
                    name="is_active"
                    :on="__('catalog.country_holiday.status.active')"
                    :off="__('catalog.country_holiday.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 3: only the date of the chosen shape is asked. --}}
            <x-catalog.form-row x-show="$wire.form.data.kind === 'fixed'">
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.country_holiday.fields.month')"
                    name="month"
                    :options="$this->monthOptions"
                    :value="$form->data?->month"
                    alpine-error="month"
                    wire:model="form.data.month"
                />

                <x-inputsform.input
                    span="short"
                    :label="__('catalog.country_holiday.fields.day')"
                    name="day"
                    type="number"
                    min="1"
                    max="31"
                    alpine-error="day"
                    wire:model="form.data.day"
                />
            </x-catalog.form-row>

            <x-catalog.form-row x-show="$wire.form.data.kind === 'easter'" x-cloak>
                <x-inputsform.input
                    span="text"
                    :label="__('catalog.country_holiday.fields.easter_offset')"
                    name="easter_offset"
                    type="number"
                    min="-60"
                    max="70"
                    :hint="__('catalog.country_holiday.fields.easter_offset_hint')"
                    alpine-error="easter_offset"
                    wire:model="form.data.easter_offset"
                />
            </x-catalog.form-row>

            <x-catalog.form-row x-show="$wire.form.data.kind === 'once'" x-cloak>
                <x-inputsform.datepicker
                    span="text"
                    :label="__('catalog.country_holiday.fields.on_date')"
                    name="on_date"
                    :hint="__('catalog.country_holiday.fields.on_date_hint')"
                    :value="$form->data?->on_date"
                    wire:model="form.data.on_date"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
