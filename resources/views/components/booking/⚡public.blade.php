<?php

use App\Classes\Main\Agenda;
use App\Dto\NotificationDto;
use App\Livewire\Forms\Agenda\PublicBookingForm;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The public booking link: the hours a business really has free, bookable
 * without writing to anybody. No session here, so every read and write runs
 * inside the business's own tenant context.
 */
new class extends Component
{
    public PublicBookingForm $form;

    #[Locked]
    public string $code = '';

    /** The booked slot, once it exists: the screen then only celebrates. */
    public ?string $booked = null;

    public ?string $warning = null;

    public function mount(string $code): void
    {
        $this->code = $code;
        $this->form->setup($this->business()->localTimezone() !== null
            ? CarbonImmutable::now($this->business()->localTimezone())->format('Y-m-d')
            : now()->format('Y-m-d'));
    }

    /** The business behind the code; anything else is a dead link. */
    public function business(): Business
    {
        $business = Business::forBookingCode($this->code);

        abort_if($business === null || $business->isSuspended(), 404);

        return $business;
    }

    /**
     * The free hours of the chosen day, keyed by themselves.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function freeHours(): array
    {
        $business = $this->business();

        $hours = app(Tenant::class)->for($business->id, fn (): array => array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            (new Agenda($business))->freeSlots(
                CarbonImmutable::parse($this->form->day, $business->localTimezone())->startOfDay(),
                $this->form->service_id,
            ),
        ));

        return array_combine($hours, $hours);
    }

    /** @return array<int, string> */
    #[Computed]
    public function serviceOptions(): array
    {
        $business = $this->business();

        return app(Tenant::class)->for($business->id, fn (): array => (new Agenda($business))->bookableServices
            ->mapWithKeys(fn (Service $service): array => [$service->id => $service->name])
            ->all());
    }

    public function updatedForm(): void
    {
        unset($this->freeHours);
    }

    public function pick(string $time): void
    {
        $this->form->time = $time;
        $this->warning = null;
    }

    public function book(): void
    {
        $result = $this->form->book($this->business());
        unset($this->freeHours);

        if ($result instanceof NotificationDto) {
            $this->warning = $result->message;

            return;
        }

        $this->booked = $this->when($result);
    }

    /** "lunes 6/10 a las 09:00", on the business's own clock. */
    private function when(Appointment $appointment): string
    {
        return $appointment->starts_at
            ->setTimezone($this->business()->localTimezone())
            ->locale(app()->getLocale())
            ->translatedFormat('l j/n H:i');
    }
};
?>

<div class="bk-page">
    @php($business = $this->business())

    <x-ui.card class="bk-card">
        @if ($booked !== null)
            <div class="bk-done">
                <span class="bk-done-icon"><x-icon name="calendar-check" :size="28" /></span>
                <h1 class="font-display text-strong text-2xl">{{ __('agenda.public.done_title') }}</h1>
                <p class="text-body text-sm">{{ __('agenda.public.done_body', ['business' => $business->name, 'when' => $booked]) }}</p>
            </div>
        @else
            <div class="bk-head">
                <p class="eyebrow">{{ $business->name }}</p>
                <h1 class="font-display text-strong text-2xl">{{ __('agenda.public.title') }}</h1>
                <p class="text-muted text-sm">{{ __('agenda.public.sub') }}</p>
            </div>

            @if ($warning !== null)
                <x-ui.alert variant="warning" class="mb-3">{{ $warning }}</x-ui.alert>
            @endif

            <div class="bp-form">
                {{-- One field per row: the card is 560px wide at its best, and
                two fields side by side clipped their own text. --}}
                <x-catalog.form-row>
                    @if ($this->serviceOptions !== [])
                        <x-inputsform.combobox
                            span="full"
                            name="service_id"
                            :label="__('agenda.form.service')"
                            :options="$this->serviceOptions"
                            :value="$form->service_id"
                            :placeholder="__('agenda.form.service_placeholder')"
                            wire:model.live="form.service_id"
                        />
                    @endif
                </x-catalog.form-row>

                <x-catalog.form-row>
                    <x-inputsform.datepicker
                        span="full"
                        name="day"
                        :label="__('agenda.form.day')"
                        :value="$form->day"
                        wire:model.live="form.day"
                    />
                </x-catalog.form-row>

                <div>
                    <p class="text-strong text-sm font-semibold">{{ __('agenda.public.pick_hour') }}</p>
                    <p class="text-muted mb-2 text-xs">{{ __('agenda.public.pick_hour_hint') }}</p>

                    @if ($this->freeHours === [])
                        <p class="text-muted py-3 text-sm">{{ __('agenda.free.none') }}</p>
                    @else
                        <div class="ag-slots">
                            @foreach ($this->freeHours as $hour)
                                <button
                                    type="button"
                                    @class(['ag-slot font-mono', 'is-picked' => $form->time === $hour])
                                    wire:key="bk-{{ $hour }}"
                                    wire:click="pick('{{ $hour }}')"
                                >
                                    {{ $hour }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('form.time')<p class="field-error-text">{{ $message }}</p>@enderror
                </div>

                <x-catalog.form-row>
                    <x-inputsform.input
                        span="full"
                        name="name"
                        :label="__('agenda.public.name')"
                        :placeholder="__('agenda.public.name_placeholder')"
                        wire:model="form.name"
                        required
                    />
                </x-catalog.form-row>

                <x-catalog.form-row>
                    <x-inputsform.input
                        span="full"
                        name="phone"
                        inputmode="tel"
                        class="font-mono"
                        :label="__('agenda.public.phone')"
                        :hint="__('agenda.public.phone_hint')"
                        wire:model="form.phone"
                        required
                    />
                </x-catalog.form-row>

                <div class="bk-actions">
                    <x-ui.button icon="calendar-check" wire:click="book">{{ __('agenda.public.submit') }}</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.card>
</div>
