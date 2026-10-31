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

    /** A holiday is switched off, never removed: the lock is here, not only in the form without a delete button. */
    public function removalCheck(int $id): array
    {
        abort(404);
    }

    public function removeRecord(int $id): bool
    {
        abort(404);
    }

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
        $this->tell((int) $this->calendarCountry, $dates);
    }

    /** One way to mail and say so: the year view and the form both end here. */
    private function tell(int $country, array $dates): void
    {
        $notice = app(NotifyBridgeHolidays::class);
        $sent = $notice->handle($country, $dates);

        $this->dispatchNotification(new NotificationDto($notice->told($sent), $sent > 0 ? NotificationType::Success : NotificationType::Info));
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
        return $this->calendarCountry === '' ? null : app(NotifyBridgeHolidays::class)->offer((int) $this->calendarCountry, $marked);
    }

    /**
     * Saving from the form: a one-year day that is new, or moved, or switched
     * on, asks the same question as the year view (the phone asks it too).
     */
    public function create(): bool
    {
        return $this->persistOffering(fn (): NotificationDto => $this->form->store(), null);
    }

    public function update(): bool
    {
        $before = $this->form->recordId === null ? null : CountryHoliday::editable($this->form->recordId);

        return $this->persistOffering(fn (): NotificationDto => $this->form->update(), $before);
    }

    /** The holiday's notice offer, after a save: to the browser, which asks before anything is mailed. */
    private function persistOffering(\Closure $save, ?CountryHoliday $before): bool
    {
        $country = $this->form->data?->country_id;
        $date = $this->form->data?->kind === CountryHoliday::KIND_ONCE ? $this->form->data->on_date : null;

        if (! $this->persist($save())) {
            return false;
        }

        // The row just written: an edit knows its id; a new one is the newest live row of that date.
        $saved = $country === null || $date === null ? null : ($before !== null ? CountryHoliday::editable($before->id) : CountryHoliday::onceOn((int) $country, $date));
        $offer = $saved === null ? null : CountryHolidayForm::offerAfterSave($before, $saved);

        if ($offer !== null) {
            $this->dispatch('holiday-notice-offer', offer: $offer, country: (int) $country);
        }

        return true;
    }

    /** The mail for a day saved from the form, once she accepted; the Action re-checks the date against the table. */
    public function noticeHoliday(int $country, array $dates): void
    {
        $this->tell($country, $dates);
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

    /** @return array<string, array{label: string, tone: string}> */
    #[Computed]
    public function kindBadges(): array
    {
        return collect((new CountryHoliday)->catalogColumns())->firstWhere('key', 'kind')->badges;
    }

    /** Every country, active or not: a row of an inactive one must still read its name when edited. */
    #[Computed]
    public function countryOptions(): array
    {
        return Country::options(label: fn (Country $country): string => $country->name);
    }

    /** The Form's lists: the phone draws the same ones. */
    #[Computed]
    public function kindOptions(): array
    {
        return CountryHolidayForm::kindOptions();
    }

    #[Computed]
    public function nthOptions(): array
    {
        return CountryHolidayForm::nthOptions();
    }

    #[Computed]
    public function weekdayOptions(): array
    {
        return CountryHolidayForm::weekdayOptions();
    }

    #[Computed]
    public function monthOptions(): array
    {
        return CountryHolidayForm::monthOptions();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="$this->searchKeys"
    :chip="$this->chipKey"
    :rules="[
        'country_id' => ['required'],
        'name' => ['required', ['minLength', 3], ['maxLength', 80], 'noMarkup'],
    ]"
>
    <x-slot:list>
        {{-- A one-year day saved from the form: ask before mailing the country's businesses, as the year view does. --}}
        <div
            hidden
            x-data
            x-on:holiday-notice-offer.window="
                const { offer, country } = $event.detail;
                if (await dialog.confirm({ title: offer.title, message: offer.message, accept: offer.accept, cancel: offer.dismiss })) {
                    $wire.noticeHoliday(country, offer.marked);
                }
            "
        ></div>

        <x-catalog.toolbar
            :search-placeholder="__('catalog.country_holiday.search_placeholder')"
            :search-label="__('catalog.country_holiday.search_label')"
            :singular="__('catalog.country_holiday.singular')"
            :plural="__('catalog.country_holiday.plural')"
            :create="__('catalog.country_holiday.create')"
            :chip-options="$this->chipOptions"
        >
            <x-ui.button variant="secondary" icon="calendar" wire:click="openCalendar" data-testid="holiday-year">
                {{ __('catalog.country_holiday.calendar.open') }}
            </x-ui.button>

            <x-ui.button variant="secondary" icon="copy" wire:click="openCopy" data-testid="holiday-copy">
                {{ __('catalog.country_holiday.copy.open') }}
            </x-ui.button>
        </x-catalog.toolbar>

        {{-- The next day off of each country, worked out by the model: the phone reads the same lines. --}}
        @if ($this->highlights !== [])
            <div class="catalog-highlights" data-testid="holiday-upcoming">
                <p class="catalog-highlights-title">{{ __('catalog.country_holiday.highlights_title') }}</p>

                <ol class="hist-list">
                    @foreach ($this->highlights as $line)
                        <li class="hist-item" wire:key="upcoming-{{ $loop->index }}">
                            <span class="hist-when font-mono">{{ $line['when'] }}</span>
                            <span class="hist-on">{{ $line['text'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        <x-catalog.table
            :empty="__('catalog.country_holiday.empty')"
            :columns="$this->tableColumns"
        >
            <td x-text="row.country"></td>
            <td class="catalog-cell-name catalog-cell-fill" x-text="row.name"></td>
            <td class="catalog-cell-sym" x-text="row.when"></td>
            <td>
                {{-- Word and tone of each kind come from the model's column, the same the phone reads. --}}
                @foreach ($this->kindBadges as $kind => $badge)
                    <span class="status-tag is-{{ $badge['tone'] }}" x-show="row.kind === @js($kind)">{{ $badge['label'] }}</span>
                @endforeach
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

            {{-- "The first Monday of February": which one, which day of the week, which month. --}}
            <x-catalog.form-row x-show="$wire.form.data.kind === 'nth'" x-cloak>
                <x-inputsform.combobox
                    span="short"
                    :label="__('catalog.country_holiday.fields.nth')"
                    name="nth"
                    :options="$this->nthOptions"
                    :value="$form->data?->nth"
                    alpine-error="nth"
                    wire:model="form.data.nth"
                />

                <x-inputsform.combobox
                    span="short"
                    :label="__('catalog.country_holiday.fields.weekday')"
                    name="weekday"
                    :options="$this->weekdayOptions"
                    :value="$form->data?->weekday"
                    alpine-error="weekday"
                    wire:model="form.data.weekday"
                />

                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.country_holiday.fields.month')"
                    name="month"
                    :hint="__('catalog.country_holiday.fields.nth_hint')"
                    :options="$this->monthOptions"
                    :value="$form->data?->month"
                    alpine-error="month"
                    wire:model="form.data.month"
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
