<?php

use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Enums\NotificationType;
use App\Actions\Catalog\NotifyBridgeHolidays;
use App\Dto\NotificationDto;
use App\Livewire\Forms\Catalog\BridgeHolidayForm;
use App\Livewire\Forms\Catalog\BridgeRangeForm;
use App\Livewire\Forms\Catalog\CopyHolidaysForm;
use App\Livewire\Forms\Catalog\CountryHolidayForm;
use App\Models\Business;
use App\Models\Country;
use App\Models\CountryHoliday;
use App\Traits\InteractsWithCatalogEditor;
use Illuminate\Support\Collection;
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

    public CopyHolidaysForm $copy;

    public BridgeHolidayForm $bridge;

    public BridgeRangeForm $range;

    /** The panel that copies one country's holidays to another is open. */
    public bool $copying = false;

    /** The year view is open, on this country (a combobox value, so a string) and year. */
    public bool $viewing = false;

    public string $calendarCountry = '';

    public int $calendarYear = 0;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    public function openCopy(): void
    {
        $this->copy->setup();
        $this->copying = true;
    }

    public function closeCopy(): void
    {
        $this->copying = false;
    }

    public function copyHolidays(): void
    {
        $notification = $this->copy->save();

        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->copying = false;
            $this->reloadTable();
        }
    }

    public function openCalendar(): void
    {
        $this->calendarYear = now()->year;
        $this->calendarCountry = (string) CountryHoliday::firstCountryId();
        $this->viewing = true;
    }

    public function closeCalendar(): void
    {
        $this->viewing = false;
    }

    /**
     * A day of the year view clicked: marked as a one-year holiday, or switched off if it already was.
     *
     * @return array{marked: list<string>, title: string, message: string, accept: string}|null The offer to mail the businesses.
     */
    public function toggleBridge(string $date): ?array
    {
        $this->bridge->country = $this->calendarCountry;
        $this->bridge->date = $date;

        $this->dispatchNotification($this->bridge->save());
        $this->refreshYear();

        return $this->noticeOffer($this->bridge->marked);
    }

    /**
     * A drag over the year view: every free weekday between the two days.
     *
     * @return array{marked: list<string>, title: string, message: string, accept: string}|null
     */
    public function bridgeRange(string $from, string $to): ?array
    {
        $this->range->country = $this->calendarCountry;
        $this->range->from = $from;
        $this->range->to = $to;

        $this->dispatchNotification($this->range->save());
        $this->refreshYear();

        return $this->noticeOffer($this->range->marked);
    }

    /** The mail to the businesses, once she accepted the offer the marking returned. */
    public function noticeBridge(array $dates): void
    {
        $sent = app(NotifyBridgeHolidays::class)->handle((int) $this->calendarCountry, $dates);

        $this->dispatchNotification(new NotificationDto(
            trans_choice('catalog.country_holiday.bridge.notified', $sent, ['count' => $sent]),
            $sent > 0 ? NotificationType::Success : NotificationType::Info,
        ));
    }

    private function refreshYear(): void
    {
        unset($this->yearHolidays);
        $this->reloadTable();
    }

    /**
     * What the browser asks next: nothing when no day became a bridge or when
     * no business of the country could be reached.
     *
     * @param  list<string>  $marked
     * @return array{marked: list<string>, title: string, message: string, accept: string}|null
     */
    private function noticeOffer(array $marked): ?array
    {
        $reach = $marked === [] || $this->calendarCountry === ''
            ? 0
            : Business::reachableInCountry((int) $this->calendarCountry)->count();

        if ($reach === 0) {
            return null;
        }

        return [
            'marked' => $marked,
            'title' => trans_choice('catalog.country_holiday.bridge.notify_title', $reach, [
                'count' => $reach,
                'country' => $this->countryName,
            ]),
            'message' => __('catalog.country_holiday.bridge.notify_message'),
            'accept' => __('catalog.country_holiday.bridge.notify_accept'),
        ];
    }

    public function shiftYear(int $step): void
    {
        $this->calendarYear = max(2000, min(2100, $this->calendarYear + $step));
    }

    /**
     * What copying would create, so the panel shows it before anything is written.
     *
     * @return Collection<int, CountryHoliday>
     */
    #[Computed]
    public function copyPreview(): Collection
    {
        if ($this->copy->source === '' || $this->copy->target === '' || $this->copy->source === $this->copy->target) {
            return new Collection;
        }

        return CountryHoliday::copyableFrom((int) $this->copy->source, (int) $this->copy->target);
    }

    /** The name of the country the year view is on; the options are a list of value/label pairs, not a map by id. */
    #[Computed]
    public function countryName(): string
    {
        return (string) (collect($this->countryOptions)->firstWhere('value', (int) $this->calendarCountry)['label'] ?? '');
    }

    /** @return Collection<int, array{date: \Carbon\CarbonImmutable, name: string}> */
    #[Computed]
    public function yearHolidays(): Collection
    {
        return $this->calendarCountry === '' ? new Collection : CountryHoliday::forYear((int) $this->calendarCountry, $this->calendarYear);
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
        >
            <x-ui.button variant="secondary" icon="calendar" wire:click="openCalendar" data-testid="holiday-year">
                {{ __('catalog.country_holiday.calendar.open') }}
            </x-ui.button>

            <x-ui.button variant="secondary" icon="copy" wire:click="openCopy" data-testid="holiday-copy">
                {{ __('catalog.country_holiday.copy.open') }}
            </x-ui.button>
        </x-catalog.toolbar>

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

        @if ($copying)
            <x-ui.slide-over
                x-on:slide-over-close="$wire.closeCopy()"
                :title="__('catalog.country_holiday.copy.title')"
                :subtitle="__('catalog.country_holiday.copy.sub')"
            >
                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="text"
                        :label="__('catalog.country_holiday.copy.source')"
                        name="source"
                        :placeholder="__('catalog.country_holiday.fields.country_placeholder')"
                        :options="$this->countryOptions"
                        :value="$copy->source"
                        wire:model.live="copy.source"
                    />

                    <x-inputsform.combobox
                        span="text"
                        :label="__('catalog.country_holiday.copy.target')"
                        name="target"
                        :placeholder="__('catalog.country_holiday.fields.country_placeholder')"
                        :options="$this->countryOptions"
                        :value="$copy->target"
                        wire:model.live="copy.target"
                    />
                </x-catalog.form-row>

                @if ($this->copyPreview->isNotEmpty())
                    <p class="bp-card-sub">{{ trans_choice('catalog.country_holiday.copy.preview', $this->copyPreview->count(), ['count' => $this->copyPreview->count()]) }}</p>

                    <ol class="hist-list" data-testid="holiday-copy-preview">
                        @foreach ($this->copyPreview as $holiday)
                            <li class="hist-item" wire:key="copy-{{ $holiday->id }}">
                                <span class="hist-when font-mono">{{ $holiday->describeWhen() }}</span>
                                <span class="hist-on">{{ $holiday->name }}</span>
                            </li>
                        @endforeach
                    </ol>
                @elseif ($copy->source !== '' && $copy->target !== '')
                    <p class="text-muted text-sm">{{ $copy->source === $copy->target ? __('catalog.country_holiday.copy.same') : __('catalog.country_holiday.copy.nothing') }}</p>
                @endif

                <x-slot:footer>
                    {{-- Only when there is something to copy: a button that answers "nothing to do" is a mute control. --}}
                    @if ($this->copyPreview->isNotEmpty())
                        <x-ui.button variant="primary" icon="copy" wire:click="copyHolidays" data-testid="holiday-copy-confirm">
                            {{ trans_choice('catalog.country_holiday.copy.accept', $this->copyPreview->count(), ['count' => $this->copyPreview->count()]) }}
                        </x-ui.button>
                    @endif
                </x-slot:footer>
            </x-ui.slide-over>
        @endif

        @if ($viewing)
            <x-ui.slide-over
                class="is-wide"
                x-on:slide-over-close="$wire.closeCalendar()"
                :title="__('catalog.country_holiday.calendar.title', ['year' => $calendarYear])"
                :subtitle="__('catalog.country_holiday.calendar.sub')"
            >
                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="long"
                        :label="__('catalog.country_holiday.fields.country_id')"
                        name="calendarCountry"
                        :options="$this->countryOptions"
                        :value="$calendarCountry"
                        wire:model.live="calendarCountry"
                    />

                    <div class="ycal-year">
                        <x-ui.icon-button icon="chevron-left" size="sm" variant="secondary" :label="__('catalog.country_holiday.calendar.previous')" wire:click="shiftYear(-1)" />
                        <span class="font-mono ycal-year-value">{{ $calendarYear }}</span>
                        <x-ui.icon-button icon="chevron-right" size="sm" variant="secondary" :label="__('catalog.country_holiday.calendar.next')" wire:click="shiftYear(1)" />
                    </div>
                </x-catalog.form-row>

                <x-catalog.year-calendar
                    :year="$calendarYear"
                    :holidays="$this->yearHolidays"
                    :country="$this->countryName"
                    :actionable="$calendarCountry !== ''"
                />
            </x-ui.slide-over>
        @endif
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
