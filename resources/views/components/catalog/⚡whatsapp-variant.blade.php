<?php

use App\Classes\Main\OpeningHours;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\WhatsappVariantForm;
use App\Models\Company;
use App\Models\SeasonalWindow;
use App\Models\WhatsappVariant;
use App\Traits\InteractsWithCatalogEditor;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the landing WhatsApp by season (`whatsapp_variants` table).
 *
 * The company row holds what the WhatsApp says all year; a row here replaces
 * it, field by field, for the days of one season. The preview is there because
 * a holiday schedule is written weeks before it runs.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public WhatsappVariantForm $form;

    /** The day the preview is asked about, ISO — what the datepicker writes. */
    public ?string $previewDate = null;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new WhatsappVariant;
    }

    /** Monday's hours onto Tuesday to Friday, the button the business's own screen has. */
    public function applyWeekdays(): void
    {
        $this->form->data->opening_schedule = OpeningHours::applyWeekdays($this->form->data->opening_schedule);
    }

    /** Switch a variant on or off from its row, with no trip to the editor. */
    public function toggleActive(int $id): void
    {
        $variant = WhatsappVariant::flipActive($id);

        if ($variant === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $this->dispatchNotification(new NotificationDto(
            __($variant->is_active ? 'catalog.whatsapp_variant.toggled_on' : 'catalog.whatsapp_variant.toggled_off', [
                'name' => $variant->seasonalWindow?->name ?? '',
            ]),
            NotificationType::Success,
        ));

        $this->reloadTable();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    #[Computed]
    public function seasonOptions(): array
    {
        return SeasonalWindow::options();
    }

    /**
     * What the landing's WhatsApp would say on the chosen day.
     *
     * @return array{phone: ?string, hours: ?string, open: ?bool, greeting: ?string, notice: ?string, season: ?string}
     */
    #[Computed]
    public function preview(): array
    {
        return Company::reach(
            $this->previewDate === null ? null : CarbonImmutable::parse($this->previewDate)->startOfDay()
        );
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['season', 'phone', 'opening_hours']"
    :rules="[
        'seasonal_window_id' => ['required'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.whatsapp_variant.search_placeholder')"
            :search-label="__('catalog.whatsapp_variant.search_label')"
            :singular="__('catalog.whatsapp_variant.singular')"
            :plural="__('catalog.whatsapp_variant.plural')"
            :create="__('catalog.whatsapp_variant.create')"
        />

        <x-catalog.table
            :empty="__('catalog.whatsapp_variant.empty')"
            :columns="[
                ['label' => __('catalog.whatsapp_variant.columns.season'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.whatsapp_variant.columns.starts_at')],
                ['label' => __('catalog.whatsapp_variant.columns.ends_at')],
                ['label' => __('catalog.whatsapp_variant.columns.phone')],
                ['label' => __('catalog.whatsapp_variant.columns.opening_hours')],
                ['label' => __('catalog.whatsapp_variant.columns.running')],
                ['label' => __('catalog.whatsapp_variant.columns.status')],
            ]"
        >
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.season"></td>
            <td class="font-mono" x-text="row.starts_at"></td>
            <td class="font-mono" x-text="row.ends_at"></td>
            {{-- Blank means "inherits", which is not the same as missing. --}}
            <td
                class="font-mono"
                x-bind:class="row.phone ? '' : 'text-muted'"
                x-text="row.phone || {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.inherits')) }}"
            ></td>
            <td
                class="catalog-cell-sym"
                x-bind:class="row.opening_hours ? '' : 'text-muted'"
                x-text="row.opening_hours || {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.inherits')) }}"
            ></td>
            <td>
                {{-- Switched on and inside its dates are different things, and
                only the second one is what the visitor is reading right now. --}}
                <span class="catalog-status" x-bind:class="row.running ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.running ? {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.running')) }} : {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.waiting')) }}"
                    ></span>
                </span>
            </td>
            {{-- The switch IS the cell: `.stop` so it does not also open the editor. --}}
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
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.whatsapp_variant.status.inactive')) }}"
                    ></span>
                </button>
            </td>
        </x-catalog.table>

        {{-- The preview: approved weeks early, read on the day it will run. --}}
        <x-ui.card class="demo-preview">
            <div class="demo-preview-head">
                <h3 class="font-display">{{ __('catalog.whatsapp_variant.preview.title') }}</h3>
                <p class="text-muted text-sm">{{ __('catalog.whatsapp_variant.preview.hint') }}</p>
            </div>

            <x-catalog.form-row>
                <x-inputsform.datepicker
                    span="short"
                    :label="__('catalog.whatsapp_variant.preview.date')"
                    name="previewDate"
                    :value="$previewDate"
                    wire:model.live="previewDate"
                />
            </x-catalog.form-row>

            <p class="text-muted text-sm">
                {{
                    $this->preview['season'] === null
                        ? __('catalog.whatsapp_variant.preview.none')
                        : __('catalog.whatsapp_variant.preview.season', ['name' => $this->preview['season']])
                }}
            </p>

            <dl class="flex flex-col gap-3 text-sm">
                @foreach (['phone', 'hours', 'notice'] as $key)
                    <div class="flex flex-col gap-0.5" wire:key="reach-{{ $key }}">
                        <dt class="text-muted">{{ __('catalog.whatsapp_variant.preview.'.$key) }}</dt>
                        <dd @class(['text-strong', 'font-mono' => $key === 'phone', 'text-muted' => blank($this->preview[$key])])>
                            {{ filled($this->preview[$key]) ? $this->preview[$key] : __('catalog.whatsapp_variant.preview.missing') }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.whatsapp_variant.new')"
            :new-title="__('catalog.whatsapp_variant.new_title')"
            :edit-title="__('catalog.whatsapp_variant.edit_title')"
            :create="__('catalog.whatsapp_variant.create')"
        >
            {{-- Row 1: which season it speaks in, and whether it is on. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="long"
                    :label="__('catalog.whatsapp_variant.fields.season')"
                    required
                    name="seasonal_window_id"
                    :placeholder="__('catalog.whatsapp_variant.fields.season_placeholder')"
                    :hint="__('catalog.whatsapp_variant.fields.season_hint')"
                    :options="$this->seasonOptions"
                    :value="$form->data?->seasonal_window_id"
                    alpine-error="seasonal_window_id"
                    wire:model="form.data.seasonal_window_id"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.whatsapp_variant.fields.status')"
                    name="is_active"
                    :on="__('catalog.whatsapp_variant.status.active')"
                    :off="__('catalog.whatsapp_variant.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 2: what changes. Each one left blank inherits the company's. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="short"
                    name="phone"
                    icon="phone"
                    class="font-mono"
                    :label="__('catalog.whatsapp_variant.fields.phone')"
                    :placeholder="__('catalog.whatsapp_variant.fields.phone_placeholder')"
                    :hint="__('catalog.whatsapp_variant.fields.phone_hint')"
                    maxlength="30"
                    wire:model="form.data.phone"
                />

                {{-- Off = this season reads the company's week. Live, because the
                grid below only exists while it is on. --}}
                <x-inputsform.switch-field
                    span="text"
                    :label="__('catalog.whatsapp_variant.fields.override_schedule')"
                    name="override_schedule"
                    :hint="__('catalog.whatsapp_variant.fields.override_schedule_hint')"
                    :on="__('catalog.whatsapp_variant.status.own_week')"
                    :off="__('catalog.whatsapp_variant.status.inherits')"
                    wire:model.live="form.data.override_schedule"
                />
            </x-catalog.form-row>

            @if ($form->data?->override_schedule)
                <x-catalog.form-row>
                    <x-inputsform.week-hours
                        span="full"
                        apply="applyWeekdays"
                        name="opening_schedule"
                        model="form.data.opening_schedule"
                        :value="$form->data->opening_schedule"
                        :label="__('catalog.whatsapp_variant.fields.opening_schedule')"
                        :hint="__('catalog.whatsapp_variant.fields.opening_schedule_hint')"
                    />
                </x-catalog.form-row>
            @endif
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
