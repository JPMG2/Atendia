<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\BusinessHour;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Opening-hours card of "Mi negocio": one row per day, several shifts per
 * row — the split day LatAm businesses actually keep. Monday can be stamped
 * onto the weekdays in one click, GBP-style. The save replaces the whole
 * week, so the screen state IS the week.
 */
new class extends Component
{
    use HasNotifications;

    /** Monday first, 0 = Sunday like date("w"), matching Schedule::week(). */
    private const DAYS = [1, 2, 3, 4, 5, 6, 0];

    /** @var array<int, list<array{opens_at: string, closes_at: string}>> */
    public array $week = [];

    public function mount(): void
    {
        $this->week = $this->weekFromStore();
    }

    /** Rebuilt per request: a Livewire component cannot hold it in a constructor. */
    protected function client(): Client
    {
        return Client::for(Auth::user());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'week' => ['array'],
            'week.*' => ['array'],
            'week.*.*.opens_at' => ['required', 'date_format:H:i'],
            'week.*.*.closes_at' => ['required', 'date_format:H:i', 'after:week.*.*.opens_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        // Laravel resolves `week.1.0.opens_at` against the wildcard key.
        return [
            'week.*.*.opens_at' => config('nicename.opens_at'),
            'week.*.*.closes_at' => config('nicename.closes_at'),
        ];
    }

    /**
     * Opening a closed day starts one SUGGESTED shift (owner's call: a filled
     * example beats two empty clocks); closing one drops them all.
     */
    public function toggleDay(int $day): void
    {
        if (! array_key_exists($day, $this->week)) {
            return;
        }

        $this->week[$day] = $this->week[$day] === [] ? [$this->suggestedShift()] : [];
    }

    public function addShift(int $day): void
    {
        if (! array_key_exists($day, $this->week)) {
            return;
        }

        $this->week[$day][] = $this->blankShift();
    }

    /** A day left without shifts reads as closed; the switch follows on its own. */
    public function removeShift(int $day, int $index): void
    {
        unset($this->week[$day][$index]);

        $this->week[$day] = array_values($this->week[$day]);
    }

    public function applyWeekdays(): void
    {
        foreach ([2, 3, 4, 5] as $day) {
            $this->week[$day] = $this->week[1];
        }
    }

    public function save(): void
    {
        $schedule = $this->client()->schedule();

        if ($schedule === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $this->validate();

        // Built from the known day keys only: an id injected into the public
        // array can never reach the table.
        $week = [];

        foreach (self::DAYS as $day) {
            $week[$day] = array_map(
                fn (array $shift): array => ['opens_at' => $shift['opens_at'], 'closes_at' => $shift['closes_at']],
                $this->week[$day] ?? [],
            );
        }

        if ($week === $this->weekFromStore()) {
            $this->dispatchNotification(new NotificationDto(__('notifications.no_changes'), NotificationType::Info));

            return;
        }

        $business = $schedule->save($week);

        $this->week = $this->weekFromStore();

        $this->dispatchNotification(resolve(NotificationService::class)->updatedRelated($business));
    }

    /**
     * The stored week as screen shifts. Postgres hands times back with
     * seconds, which the time inputs and the comparison must not see.
     *
     * @return array<int, list<array{opens_at: string, closes_at: string}>>
     */
    private function weekFromStore(): array
    {
        $stored = $this->client()->schedule()?->week() ?? [];

        return collect(self::DAYS)
            ->mapWithKeys(fn (int $day): array => [$day => array_map(
                fn (BusinessHour $shift): array => [
                    'opens_at' => substr($shift->opens_at, 0, 5),
                    'closes_at' => substr($shift->closes_at, 0, 5),
                ],
                $stored[$day] ?? [],
            )])
            ->all();
    }

    /**
     * @return array{opens_at: string, closes_at: string}
     */
    private function suggestedShift(): array
    {
        return ['opens_at' => '09:00', 'closes_at' => '18:00'];
    }

    /**
     * A SECOND shift starts empty: the split of a day is the business's own.
     *
     * @return array{opens_at: string, closes_at: string}
     */
    private function blankShift(): array
    {
        return ['opens_at' => '', 'closes_at' => ''];
    }
};
?>

<x-ui.card class="bp-card" data-section="horarios">
    @php
        $days = App\Models\BusinessHour::dayNames();
    @endphp

    <div class="bp-card-head">
        <h2>{{ __('client.business.hours.title') }}</h2>
        <span class="bp-chip">{{ __('client.business.recommended') }}</span>
        <span class="bp-head-action">
            <x-ui.button variant="secondary" size="sm" wire:click="applyWeekdays">{{ __('client.business.hours.apply_weekdays') }}</x-ui.button>
        </span>
    </div>
    <p class="bp-card-sub">{{ __('client.business.hours.sub') }}</p>

    <div class="bp-hours">
        @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
            <div wire:key="day-{{ $day }}" @class(['bp-hrow', 'is-closed' => $week[$day] === []])>
                <span class="bp-hday">{{ $days[$day] }}</span>
                <x-ui.switch name="day-{{ $day }}" size="sm" :checked="$week[$day] !== []"
                    :aria-label="$days[$day]" wire:click="toggleDay({{ $day }})" />

                <div class="bp-hshifts">
                    @forelse ($week[$day] as $index => $shift)
                        <span class="bp-shift-edit" wire:key="shift-{{ $day }}-{{ $index }}">
                            {{-- Plain text and not type="time": the native picker clips
                            the minutes at this width; the server validates H:i. --}}
                            <x-inputsform.input type="text" size="s" maxlength="5" inputmode="numeric"
                                :id="'bp-h-'.$day.'-'.$index.'-opens'" :placeholder="__('client.business.hours.time_placeholder')"
                                :name="'week.'.$day.'.'.$index.'.opens_at'" :aria-label="$days[$day].' · '.__('client.business.hours.opens')"
                                class="font-mono" wire:model="week.{{ $day }}.{{ $index }}.opens_at" />
                            <span class="bp-shift-sep" aria-hidden="true">–</span>
                            <x-inputsform.input type="text" size="s" maxlength="5" inputmode="numeric"
                                :id="'bp-h-'.$day.'-'.$index.'-closes'" :placeholder="__('client.business.hours.time_placeholder')"
                                :name="'week.'.$day.'.'.$index.'.closes_at'" :aria-label="$days[$day].' · '.__('client.business.hours.closes')"
                                class="font-mono" wire:model="week.{{ $day }}.{{ $index }}.closes_at" />
                            <x-ui.icon-button icon="trash-2" variant="ghost" class="bp-shift-remove"
                                data-testid="shift-remove" :label="__('client.business.hours.remove_shift')"
                                wire:click="removeShift({{ $day }}, {{ $index }})" />
                        </span>
                    @empty
                        <span class="bp-closed">{{ __('client.business.hours.closed') }}</span>
                    @endforelse
                </div>

                @if ($week[$day] !== [])
                    <button type="button" class="bp-hadd" wire:click="addShift({{ $day }})">+ {{ __('client.business.hours.add_shift') }}</button>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
