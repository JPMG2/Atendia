<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\DemoTagForm;
use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use App\Traits\InteractsWithCatalogEditor;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor for the hero demo examples master (`demo_tags` table).
 *
 * A row with no season is what the landing shows all year; a row with one
 * replaces it for those days, field by field. The preview below the list is
 * there because a season is approved weeks before it runs, and nobody should
 * have to wait until December to find out what December looks like.
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public DemoTagForm $form;

    /** The day the preview is asked about, ISO — what the datepicker writes. */
    public ?string $previewDate = null;

    /** Which example the preview phone is replaying; null means the first. */
    public ?string $previewSlug = null;

    public function previewTag(string $slug): void
    {
        $this->previewSlug = $slug;
    }

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new DemoTag;
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
     * What the hero would open with on the chosen day.
     *
     * @return list<array{slug: string, label: string, name: string, noun: string, chips: list<string>, pool: list<array{side: string, text: string}>, season: ?string}>
     */
    #[Computed]
    public function preview(): array
    {
        return DemoTag::resolved($this->previewDay());
    }

    /** The season talking on the previewed day, if any is. */
    #[Computed]
    public function previewSeason(): ?string
    {
        return SeasonalWindow::inForce($this->previewDay())->first()?->name;
    }

    /**
     * The example the phone is showing. Falls back to the first, which is what
     * a visitor lands on: the preview must open on what the hero opens on.
     *
     * @return array{slug: string, label: string, name: string, noun: string, chips: list<string>, pool: list<array{side: string, text: string}>, season: ?string}|null
     */
    #[Computed]
    public function previewing(): ?array
    {
        $tags = collect($this->preview);

        return $tags->firstWhere('slug', $this->previewSlug) ?? $tags->first();
    }

    private function previewDay(): CarbonImmutable
    {
        return $this->previewDate === null
            ? SeasonalWindow::today()
            : CarbonImmutable::parse($this->previewDate)->startOfDay();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['slug', 'label', 'business_name', 'season']"
    :rules="[
        'slug' => ['required', ['minLength', 2], ['maxLength', 40], 'noMarkup'],
    ]"
>
    {{-- Table view: the list. --}}
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.demo_tag.search_placeholder')"
            :search-label="__('catalog.demo_tag.search_label')"
            :singular="__('catalog.demo_tag.singular')"
            :plural="__('catalog.demo_tag.plural')"
            :create="__('catalog.demo_tag.create')"
        />

        <x-catalog.table
            :empty="__('catalog.demo_tag.empty')"
            :columns="[
                ['label' => __('catalog.demo_tag.columns.slug')],
                ['label' => __('catalog.demo_tag.columns.label'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.demo_tag.columns.business_name')],
                ['label' => __('catalog.demo_tag.columns.season')],
                ['label' => __('catalog.demo_tag.columns.sort_order')],
                ['label' => __('catalog.demo_tag.columns.status')],
            ]"
        >
            <td><span class="catalog-code" x-text="row.slug"></span></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.label"></td>
            {{-- Blank on a variant means "inherits", which is not the same as
            missing: said out loud, or the row reads as half loaded. --}}
            <td
                class="catalog-cell-sym"
                x-bind:class="! row.business_name && row.season ? 'text-muted' : ''"
                x-text="row.business_name || (row.season ? {{ \Illuminate\Support\Js::from(__('catalog.demo_tag.status.inherits')) }} : '')"
            ></td>
            <td
                class="catalog-cell-sym"
                x-text="row.season || {{ \Illuminate\Support\Js::from(__('catalog.demo_tag.status.evergreen')) }}"
            ></td>
            <td class="font-mono" x-text="row.sort_order"></td>
            <td>
                <span class="catalog-status" x-bind:class="row.active ? 'is-on' : 'is-off'">
                    <span class="dot"></span
                    ><span
                        x-text="row.active ? {{ \Illuminate\Support\Js::from(__('catalog.demo_tag.status.active')) }} : {{ \Illuminate\Support\Js::from(__('catalog.demo_tag.status.inactive')) }}"
                    ></span>
                </span>
            </td>
        </x-catalog.table>

        {{-- The preview: approved weeks early, read on the day it will run. --}}
        <x-ui.card class="demo-preview">
            <div class="demo-preview-head">
                <h3 class="font-display">{{ __('catalog.demo_tag.preview.title') }}</h3>
                <p class="text-muted text-sm">{{ __('catalog.demo_tag.preview.hint') }}</p>
            </div>

            <x-catalog.form-row>
                <x-inputsform.datepicker
                    span="short"
                    :label="__('catalog.demo_tag.preview.date')"
                    name="previewDate"
                    :value="$previewDate"
                    wire:model.live="previewDate"
                />
            </x-catalog.form-row>

            <p class="text-muted text-sm">
                {{
                    $this->previewSeason === null
                        ? __('catalog.demo_tag.preview.none')
                        : __('catalog.demo_tag.preview.season', ['name' => $this->previewSeason])
                }}
            </p>

            @if ($this->preview === [])
                <p class="text-muted text-sm">{{ __('catalog.demo_tag.preview.empty') }}</p>
            @else
                {{-- The pills are the hero's own selector, so here they pick
                too: seeing the pill is not seeing what the visitor reads. --}}
                <div class="demo-preview-pills">
                    @foreach ($this->preview as $tag)
                        <button
                            type="button"
                            class="demo-preview-pill"
                            @class(['is-on' => $this->previewing['slug'] === $tag['slug']])
                            wire:key="preview-{{ $tag['slug'] }}"
                            wire:click="previewTag('{{ $tag['slug'] }}')"
                        >
                            <span class="pill-live-dot"></span>{{ $tag['label'] }}
                        </button>
                    @endforeach
                </div>

                @if ($this->previewing !== null)
                    <div class="demo-preview-phone">
                        <div class="demo-preview-phone-head">
                            <span class="demo-preview-phone-avatar"><x-icon name="bot" :size="16" /></span>
                            <span>{{ __('landing.demo.header', ['name' => $this->previewing['name']]) }}</span>
                        </div>

                        <div class="demo-preview-phone-body">
                            @forelse ($this->previewing['pool'] as $index => $line)
                                <div class="pm-row {{ $line['side'] }}" wire:key="bubble-{{ $this->previewing['slug'] }}-{{ $index }}">
                                    <div class="pm-bubble {{ $line['side'] }}">{{ $line['text'] }}</div>
                                </div>
                            @empty
                                <p class="text-muted text-sm">{{ __('catalog.demo_tag.preview.no_script') }}</p>
                            @endforelse
                        </div>

                        @if ($this->previewing['chips'] !== [])
                            <div class="demo-preview-phone-chips">
                                @foreach ($this->previewing['chips'] as $chip)
                                    <span wire:key="chip-{{ $this->previewing['slug'] }}-{{ $loop->index }}">{{ $chip }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </x-ui.card>
    </x-slot:list>

    {{-- Form view: create and edit. --}}
    <x-slot:form>
        <x-catalog.form-shell
            :new="__('catalog.demo_tag.new')"
            :new-title="__('catalog.demo_tag.new_title')"
            :edit-title="__('catalog.demo_tag.edit_title')"
            :create="__('catalog.demo_tag.create')"
        >
            {{-- Row 1: the key and the two names it stands for. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="code"
                    :label="__('catalog.demo_tag.fields.slug')"
                    required
                    name="slug"
                    :hint="__('catalog.demo_tag.fields.slug_hint')"
                    maxlength="40"
                    alpine-error="slug"
                    wire:model="form.data.slug"
                />

                <x-inputsform.input
                    span="short"
                    :label="__('catalog.demo_tag.fields.label')"
                    name="label"
                    :placeholder="__('catalog.demo_tag.fields.label_placeholder')"
                    maxlength="255"
                    wire:model="form.data.label"
                />

                <x-inputsform.input
                    span="text"
                    :label="__('catalog.demo_tag.fields.business_name')"
                    name="business_name"
                    :placeholder="__('catalog.demo_tag.fields.business_name_placeholder')"
                    maxlength="255"
                    wire:model="form.data.business_name"
                />
            </x-catalog.form-row>

            {{-- Row 2: when it applies, where it sits, and whether it is on. --}}
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.demo_tag.fields.season')"
                    name="seasonal_window_id"
                    :placeholder="__('catalog.demo_tag.fields.season_placeholder')"
                    :hint="__('catalog.demo_tag.fields.season_hint')"
                    :options="$this->seasonOptions"
                    :value="$form->data?->seasonal_window_id"
                    wire:model="form.data.seasonal_window_id"
                />

                <x-inputsform.input
                    span="short"
                    :label="__('catalog.demo_tag.fields.noun')"
                    name="noun"
                    :hint="__('catalog.demo_tag.fields.noun_hint')"
                    maxlength="60"
                    wire:model="form.data.noun"
                />

                <x-inputsform.input
                    span="code"
                    :label="__('catalog.demo_tag.fields.sort_order')"
                    required
                    name="sort_order"
                    type="number"
                    min="0"
                    :hint="__('catalog.demo_tag.fields.sort_order_hint')"
                    alpine-error="sort_order"
                    wire:model="form.data.sort_order"
                />

                <x-inputsform.switch-field
                    span="short"
                    :label="__('catalog.demo_tag.fields.status')"
                    name="is_active"
                    :on="__('catalog.demo_tag.status.active')"
                    :off="__('catalog.demo_tag.status.inactive')"
                    wire:model="form.data.is_active"
                />
            </x-catalog.form-row>

            {{-- Row 3 and 4: the content itself, each line a chip or a bubble. --}}
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    :label="__('catalog.demo_tag.fields.chips')"
                    name="chips"
                    :rows="3"
                    :hint="__('catalog.demo_tag.fields.chips_hint')"
                    wire:model="form.data.chips"
                />
            </x-catalog.form-row>

            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    :label="__('catalog.demo_tag.fields.pool')"
                    name="pool"
                    :rows="5"
                    :hint="__('catalog.demo_tag.fields.pool_hint')"
                    wire:model="form.data.pool"
                />
            </x-catalog.form-row>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
