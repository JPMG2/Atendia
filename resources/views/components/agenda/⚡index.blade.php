<?php

use App\Classes\Main\Agenda;
use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Livewire\Forms\Agenda\AppointmentForm;
use App\Models\Appointment;
use App\Models\Service;
use App\Traits\HasNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Agenda": one day of bookings and the hours still free. Owner-only by its
 * route; every write goes through the client's Agenda piece, pinned to this
 * business. The switch and the capacity live in "Mi negocio · Turnos", where
 * the rest of the business setup is.
 */
new class extends Component
{
    use HasNotifications;

    public AppointmentForm $form;

    /** The day on screen, ISO — what the datepicker writes. */
    public string $day = '';

    /** day | week: the same agenda read close up or whole. */
    public string $view = 'day';

    public bool $sheetOpen = false;

    public function mount(): void
    {
        $this->day = ($this->agenda?->today ?? CarbonImmutable::now())->format('Y-m-d');
        $this->form->setup($this->day);
    }

    #[Computed]
    public function agenda(): ?Agenda
    {
        return Client::for(Auth::user())->agenda;
    }

    /**
     * Whether there is an agenda to paint at all. Off, the slot finder gives
     * no hours, so every card below would blame the day for what the switch
     * did — and a business yet to be born has no agenda piece to ask.
     */
    #[Computed]
    public function isOn(): bool
    {
        return $this->agenda?->isOn ?? false;
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->agenda->day($this->dayDate());
    }

    /**
     * The free hours of the day on screen, keyed by themselves so the picker
     * shows and stores "HH:MM".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function freeHours(): array
    {
        return $this->hoursFor($this->day);
    }

    /**
     * The week that holds the day on screen.
     *
     * @return list<array{date: CarbonImmutable, bookings: Collection<int, Appointment>, free: int, closed: bool}>
     */
    #[Computed]
    public function weekDays(): array
    {
        return $this->agenda->week($this->dayDate());
    }

    /** @return array<int, string> */
    #[Computed]
    public function customerOptions(): array
    {
        return $this->agenda->customerOptions;
    }

    /** @return array<int, string> */
    #[Computed]
    public function serviceOptions(): array
    {
        return $this->agenda->bookableServices
            ->mapWithKeys(fn (Service $service): array => [$service->id => $service->name])
            ->all();
    }

    /**
     * The hours offered for a day. A booking being moved keeps its own hour
     * on the list: it is where it already is.
     *
     * @return array<string, string>
     */
    public function hoursFor(string $day, ?string $keep = null): array
    {
        $hours = array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            $this->agenda->freeSlots(CarbonImmutable::parse($day, $this->timezone())->startOfDay()),
        );

        if ($keep !== null && ! in_array($keep, $hours, true)) {
            $hours[] = $keep;
            sort($hours);
        }

        return array_combine($hours, $hours);
    }

    public function go(int $days): void
    {
        // In the week view the arrows move a whole week: a week at a time is
        // what "previous" means when seven days are on screen.
        $this->day = $this->dayDate()->addDays($this->view === 'week' ? $days * 7 : $days)->format('Y-m-d');
        $this->refreshDay();
    }

    public function showDay(?string $day = null): void
    {
        $this->day = $day ?? $this->day;
        $this->view = 'day';
        $this->refreshDay();
    }

    public function showWeek(): void
    {
        $this->view = 'week';
        $this->refreshDay();
    }

    public function today(): void
    {
        $this->day = $this->agenda->today->format('Y-m-d');
        $this->refreshDay();
    }

    public function updatedDay(): void
    {
        $this->refreshDay();
    }

    public function book(?string $time = null): void
    {
        $this->form->setup($this->day, null, $time);
        $this->sheetOpen = true;
    }

    public function move(int $appointmentId): void
    {
        $this->form->setup($this->day, $appointmentId);
        $this->sheetOpen = true;
    }

    public function save(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->sheetOpen = false;
            $this->day = $this->form->day;
            $this->refreshDay();
        }
    }

    public function cancel(int $appointmentId): void
    {
        $this->agenda->cancel($appointmentId);
        $this->refreshDay();
        $this->dispatchNotification(new NotificationDto(__('agenda.notify.cancelled'), NotificationType::Success));
    }

    public function markDone(int $appointmentId): void
    {
        $this->agenda->mark($appointmentId, AppointmentStatus::Done);
        $this->refreshDay();
        $this->dispatchNotification(new NotificationDto(__('agenda.notify.marked'), NotificationType::Success));
    }

    public function timezone(): string
    {
        return Auth::user()->business->localTimezone();
    }

    private function dayDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->day, $this->timezone())->startOfDay();
    }

    private function refreshDay(): void
    {
        unset($this->bookings, $this->freeHours, $this->weekDays);
    }
};
?>

<div>
    <x-ui.page-head :title="__('agenda.title')" :sub="__('agenda.sub')">
        @if ($this->isOn)
            <x-ui.button size="sm" icon="plus" wire:click="book">{{ __('agenda.book') }}</x-ui.button>
        @endif
    </x-ui.page-head>

    @if (! $this->isOn)
        {{-- The switch is down (or there is no business yet): naming the real
        reason, because the cards below would blame the day for it. --}}
        <x-ui.card class="p-6">
            <x-ui.empty-state icon="calendar-check" :title="__('agenda.off.title')" :body="__('agenda.off.body')">
                <x-ui.button variant="primary" icon="calendar-check" :href="route('my-business.turnos')" wire:navigate>{{ __('agenda.off.cta') }}</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else

    @php($onScreen = \Carbon\CarbonImmutable::parse($this->day))

    <x-ui.card class="bp-card">
        <div class="bp-card-head">
            <h2>
                @if ($view === 'week')
                    {{ __('agenda.week_of', ['day' => $onScreen->startOfWeek()->translatedFormat('j/n')]) }}
                @else
                    {{ \Illuminate\Support\Str::ucfirst($onScreen->locale(app()->getLocale())->translatedFormat('l j \d\e F')) }}
                @endif
            </h2>
            <span class="bp-chip font-mono">{{ $view === 'week' ? collect($this->weekDays)->sum(fn (array $slot): int => $slot['bookings']->count()) : $this->bookings->count() }}</span>
            <span class="bp-head-action ag-switch">
                <x-ui.button :variant="$view === 'day' ? 'primary' : 'ghost'" size="sm" wire:click="showDay">{{ __('agenda.view_day') }}</x-ui.button>
                <x-ui.button :variant="$view === 'week' ? 'primary' : 'ghost'" size="sm" wire:click="showWeek">{{ __('agenda.view_week') }}</x-ui.button>
            </span>
        </div>

        {{-- The day picker and its arrows are one toolbar row: outside a
        declared row the span would be inert and the field ragged. --}}
        <x-catalog.form-row>
            <x-inputsform.datepicker
                span="short"
                size="s"
                name="day"
                :value="$this->day"
                :aria-label="__('agenda.pick_day')"
                wire:model.live="day"
            />
            <div class="ag-nav flex-none self-center">
                <x-ui.icon-button icon="chevron-left" size="sm" variant="ghost" :label="__('agenda.previous')" wire:click="go(-1)" />
                <x-ui.button variant="secondary" size="sm" wire:click="today">{{ __('agenda.today') }}</x-ui.button>
                <x-ui.icon-button icon="chevron-right" size="sm" variant="ghost" :label="__('agenda.next')" wire:click="go(1)" />
            </div>
        </x-catalog.form-row>

        @if ($view === 'week')
            {{-- The week reads as seven short columns: press a day to open it. --}}
            <div class="ag-week">
                @foreach ($this->weekDays as $slot)
                    @php($isOnScreen = $slot['date']->format('Y-m-d') === $this->day)
                    <button
                        type="button"
                        @class(['ag-day', 'is-current' => $isOnScreen])
                        wire:key="week-{{ $slot['date']->format('Y-m-d') }}"
                        wire:click="showDay('{{ $slot['date']->format('Y-m-d') }}')"
                    >
                        <span class="ag-day-head">
                            <span class="text-strong text-sm font-semibold">{{ \Illuminate\Support\Str::ucfirst($slot['date']->locale(app()->getLocale())->translatedFormat('D')) }}</span>
                            <span class="text-muted font-mono text-xs">{{ $slot['date']->format('j/n') }}</span>
                        </span>

                        @forelse ($slot['bookings'] as $booking)
                            <span class="ag-day-item" wire:key="wk-{{ $booking->id }}">
                                <span class="font-mono">{{ $booking->starts_at->setTimezone($this->timezone())->format('H:i') }}</span>
                                {{-- Wrapped, never cut: a name the owner cannot read is no name. --}}
                                <span class="break-words">{{ $booking->customer?->displayName() ?? __('agenda.no_name') }}</span>
                            </span>
                        @empty
                            <span class="text-subtle text-xs">
                                {{ $slot['closed'] ? __('agenda.week_closed') : __('agenda.week_none') }}
                            </span>
                        @endforelse

                        <span class="ag-day-free">{{ trans_choice('agenda.week_free', $slot['free'], ['count' => $slot['free']]) }}</span>
                    </button>
                @endforeach
            </div>
        @elseif ($this->bookings->isEmpty())
            <x-ui.empty-state icon="calendar" :title="__('agenda.empty.title')" :body="__('agenda.empty.body')" compact />
        @else
            <ul class="ag-list">
                @foreach ($this->bookings as $booking)
                    @php($local = $booking->starts_at->setTimezone($this->timezone()))
                    <li class="ag-row" wire:key="booking-{{ $booking->id }}">
                        <span class="ag-time font-mono">{{ $local->format('H:i') }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="text-strong flex flex-wrap items-center gap-2 text-sm font-semibold">
                                <span>{{ $booking->customer?->displayName() ?? __('agenda.no_name') }}</span>
                                @if ($booking->status !== AppointmentStatus::Confirmed)
                                    <span class="ag-state">{{ __('agenda.status.'.$booking->status->value) }}</span>
                                @endif
                            </p>
                            <p class="text-muted text-xs">
                                {{-- Each datum wraps whole: a phone split mid-way reads as two. --}}
                                <span class="inline-block">{{ $booking->service?->name ?? __('agenda.plain_slot') }}</span>
                                <span class="inline-block whitespace-nowrap">· <span class="font-mono">{{ $booking->durationMinutes() }} min</span></span>
                                @if ($booking->customer?->phone !== null)
                                    <span class="inline-block whitespace-nowrap">· <span class="font-mono">+{{ $booking->customer->phone }}</span></span>
                                @endif
                            </p>
                            @if ($booking->notes !== null)
                                <p class="text-muted mt-0.5 text-xs">{{ $booking->notes }}</p>
                            @endif
                        </div>

                        @if ($booking->status === AppointmentStatus::Confirmed)
                            <div class="flex flex-none items-center gap-1">
                                <x-ui.icon-button icon="check" size="sm" variant="ghost" :label="__('agenda.mark_done')" wire:click="markDone({{ $booking->id }})" />
                                <x-ui.icon-button icon="clock" size="sm" variant="ghost" :label="__('agenda.move')" wire:click="move({{ $booking->id }})" />
                                <x-ui.icon-button
                                    icon="x"
                                    size="sm"
                                    variant="danger"
                                    :label="__('agenda.cancel')"
                                    x-data
                                    x-on:click="dialog.confirm({
                                        title: {{ Js::from(__('agenda.cancel_title')) }},
                                        message: {{ Js::from(__('agenda.cancel_message', ['when' => $local->format('H:i')])) }},
                                        accept: {{ Js::from(__('agenda.cancel_accept')) }},
                                        type: 'danger',
                                    }).then((yes) => yes && $wire.cancel({{ $booking->id }}))"
                                />
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- The free hours belong to ONE day: in the week view each column carries its own count. --}}
    @if ($view === 'day')
    <x-ui.card class="bp-card">
        <div class="bp-card-head">
            <h2>{{ __('agenda.free.title') }}</h2>
            <span class="bp-chip font-mono">{{ count($this->freeHours) }}</span>
        </div>
        <p class="bp-card-sub">{{ __('agenda.free.sub') }}</p>

        @if ($this->freeHours === [])
            <p class="text-muted py-3 text-center text-sm">{{ __('agenda.free.none') }}</p>
        @else
            <div class="ag-slots">
                @foreach ($this->freeHours as $hour)
                    <button type="button" class="ag-slot font-mono" data-testid="free-hour-{{ str_replace(':', '', $hour) }}" wire:key="slot-{{ $hour }}" wire:click="book('{{ $hour }}')">
                        {{ $hour }}
                    </button>
                @endforeach
            </div>
        @endif
    </x-ui.card>
    @endif

    @if ($sheetOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.set('sheetOpen', false)"
            :title="$form->editingId === null ? __('agenda.form.title') : __('agenda.form.move_title')"
            :subtitle="$form->editingId === null ? __('agenda.form.sub') : __('agenda.form.move_sub')"
        >
            <div class="bp-form">
                @if ($form->editingId === null)
                    <x-catalog.form-row>
                        <x-inputsform.combobox
                            span="full"
                            name="customer_id"
                            :label="__('agenda.form.customer')"
                            :options="$this->customerOptions"
                            :value="$form->customer_id"
                            :placeholder="__('agenda.form.customer_placeholder')"
                            :empty="__('agenda.form.customer_empty')"
                            wire:model="form.customer_id"
                            required
                        />
                    </x-catalog.form-row>

                    @if ($this->serviceOptions !== [])
                        <x-catalog.form-row>
                            <x-inputsform.combobox
                                span="full"
                                name="service_id"
                                :label="__('agenda.form.service')"
                                :options="$this->serviceOptions"
                                :value="$form->service_id"
                                :placeholder="__('agenda.form.service_placeholder')"
                                wire:model="form.service_id"
                            />
                        </x-catalog.form-row>
                    @endif
                @endif

                <x-catalog.form-row>
                    <x-inputsform.datepicker
                        span="text"
                        name="form_day"
                        :label="__('agenda.form.day')"
                        :value="$form->day"
                        wire:model.live="form.day"
                    />
                    {{-- Not "short": inside the sheet the hour shares the row
                    with the day, and its own value came out clipped ("08:0…")
                    behind the clear and the chevron. --}}
                    <x-inputsform.combobox
                        span="text"
                        name="time"
                        :label="__('agenda.form.time')"
                        :options="$this->hoursFor($form->day, $form->editingId === null ? null : $form->time)"
                        :value="$form->time"
                        :placeholder="__('agenda.form.time_placeholder')"
                        :empty="__('agenda.free.none')"
                        loading="form.day"
                        wire:model="form.time"
                        required
                    />
                </x-catalog.form-row>

                @if ($form->editingId === null)
                    <x-catalog.form-row>
                        <x-inputsform.textarea
                            span="full"
                            name="notes"
                            :rows="2"
                            :label="__('agenda.form.notes')"
                            :hint="__('agenda.form.notes_hint')"
                            wire:model="form.notes"
                        />
                    </x-catalog.form-row>
                @endif
            </div>

            <x-slot:footer>
                <x-ui.button variant="danger" wire:click="$set('sheetOpen', false)">{{ __('agenda.form.cancel') }}</x-ui.button>
                <x-ui.button class="ml-auto" icon="check" wire:click="save">
                    {{ $form->editingId === null ? __('agenda.form.save') : __('agenda.form.move_save') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
    @endif
</div>
