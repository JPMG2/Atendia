<?php

use App\Classes\Main\Client;
use App\Classes\Main\Professionals;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\ProfessionalForm;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Profesionales": whoever attends the bookings, each with their own week,
 * capacity and services. Owner-only by its route; every write goes through the
 * client's Professionals piece, pinned to this business.
 */
new class extends Component
{
    use HasNotifications;

    public ProfessionalForm $professional;

    public bool $sheetOpen = false;

    public function mount(): void
    {
        $this->professional->setup();
    }

    #[Computed]
    public function professionals(): ?Professionals
    {
        return Client::for(Auth::user())->professionals;
    }

    public function open(?int $professionalId = null): void
    {
        $this->professional->setup($professionalId);
        $this->sheetOpen = true;
    }

    public function save(): void
    {
        $notification = $this->professional->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->sheetOpen = false;
            unset($this->professionals);
        }
    }

    public function remove(): void
    {
        $notification = $this->professional->remove();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->sheetOpen = false;
            unset($this->professionals);
        }
    }
};
?>

<div>
    <x-ui.page-head :title="__('professionals.title')" :sub="__('professionals.sub')" />

    @if ($this->professionals === null)
        {{-- Everything hangs off the business: without one there is nothing to read. --}}
        <x-ui.card class="p-6">
            <x-ui.empty-state icon="user" :title="__('professionals.no_business.title')" :body="__('professionals.no_business.body')">
                <x-ui.button variant="primary" icon="store" :href="route('onboarding')" wire:navigate>{{ __('professionals.no_business.cta') }}</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else

    @php($pieces = $this->professionals)

    @if ($pieces->bookableServices->isEmpty())
        <x-ui.alert variant="info" class="mb-4">
            {{ __('professionals.needs_services.body') }}
            <a class="underline" href="{{ route('my-services') }}" wire:navigate>{{ __('professionals.needs_services.cta') }}</a>
        </x-ui.alert>
    @endif

    <x-ui.card class="bp-card">
        <div class="bp-card-head">
            <h2>{{ __('professionals.list.title') }}</h2>
            <span class="bp-chip font-mono">{{ $pieces->all->count() }}</span>
            <x-ui.button class="ml-auto" size="sm" icon="plus" wire:click="open">
                {{ __('professionals.list.new') }}
            </x-ui.button>
        </div>

        @if ($pieces->all->isEmpty())
            <x-ui.empty-state compact icon="user" :title="__('professionals.list.empty_title')" :body="__('professionals.list.empty_body')" />
        @endif

        <ul class="tm-list">
            @foreach ($pieces->all as $staff)
                <li class="tm-row" wire:key="pro-{{ $staff->id }}">
                    <x-ui.avatar :name="$staff->name" size="md" />

                    <div class="min-w-0 flex-1">
                        <p class="text-strong flex flex-wrap items-center gap-2 text-sm font-semibold">
                            <span>{{ $staff->name }}</span>
                            @unless ($staff->is_active)
                                <span class="status-tag is-warning">{{ __('professionals.list.inactive') }}</span>
                            @endunless
                        </p>
                        <p class="text-muted text-xs">
                            {{ $pieces->hoursLine($staff) }}
                            · {{ trans_choice('professionals.list.services', $staff->services->count(), ['count' => $staff->services->count()]) }}
                            @if ($staff->appointments_per_day !== null)
                                · <span class="font-mono">{{ __('professionals.list.limits', ['per_day' => $staff->appointments_per_day]) }}</span>
                            @endif
                            @if ($pieces->upcomingLine($staff) !== null)
                                · {{ $pieces->upcomingLine($staff) }}
                            @endif
                        </p>
                    </div>

                    <x-ui.icon-button icon="pencil" size="sm" variant="ghost" :label="__('professionals.list.edit', ['name' => $staff->name])" wire:click="open({{ $staff->id }})" />
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    @if ($sheetOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.set('sheetOpen', false)"
            :title="$professional->editingId === null ? __('professionals.sheet.new_title') : __('professionals.sheet.edit_title', ['name' => $professional->data->name])"
        >
            <div class="bp-form">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="name" :label="__('professionals.fields.name')" wire:model="professional.data.name" required />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="short" name="appointment_capacity" :label="__('professionals.fields.capacity')" :hint="__('professionals.fields.capacity_hint')" class="font-mono" inputmode="numeric" wire:model="professional.data.appointment_capacity" />
                    <x-inputsform.input span="short" name="appointments_per_day" :label="__('professionals.fields.per_day')" :hint="__('professionals.fields.per_day_hint')" class="font-mono" inputmode="numeric" wire:model="professional.data.appointments_per_day" />
                    <x-inputsform.switch-field span="short" name="is_active" :label="__('professionals.sheet.active')" :hint="__('professionals.sheet.active_hint')" :on="__('professionals.sheet.active_on')" :off="__('professionals.sheet.active_off')" wire:model="professional.data.is_active" />
                </x-catalog.form-row>

                <div>
                    <p class="text-strong text-sm font-semibold">{{ __('professionals.hours.label') }}</p>
                    <p class="text-muted mb-2 text-xs">{{ __('professionals.hours.hint') }}</p>
                    <x-catalog.form-row>
                        <x-inputsform.switch-field
                            span="full"
                            name="uses_business_hours"
                            :label="__('professionals.hours.use_business')"
                            :on="__('professionals.hours.switch_on')"
                            :off="__('professionals.hours.switch_off')"
                            wire:model.live="professional.data.uses_business_hours"
                        />
                    </x-catalog.form-row>

                    @unless ($professional->data->uses_business_hours)
                        <div class="tm-hours">
                            @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
                                @php($dayName = ucfirst(now()->startOfWeek()->addDays(($day + 6) % 7)->locale(app()->getLocale())->isoFormat('ddd')))
                                <x-catalog.form-row class="tm-hour-row" wire:key="ph-{{ $day }}">
                                    <span class="tm-hour-day">{{ $dayName }}</span>
                                    <x-ui.switch name="week-{{ $day }}" size="sm" :aria-label="$dayName" wire:model.live="professional.data.week.{{ $day }}.open" />
                                    @if ($professional->data->week[$day]['open'])
                                        <x-inputsform.input span="code" size="s" maxlength="5" inputmode="numeric" class="font-mono" name="week.{{ $day }}.opens_at" :aria-label="$dayName.' · '.__('professionals.hours.opens')" wire:model="professional.data.week.{{ $day }}.opens_at" />
                                        <span class="bp-shift-sep" aria-hidden="true">–</span>
                                        <x-inputsform.input span="code" size="s" maxlength="5" inputmode="numeric" class="font-mono" name="week.{{ $day }}.closes_at" :aria-label="$dayName.' · '.__('professionals.hours.closes')" wire:model="professional.data.week.{{ $day }}.closes_at" />
                                    @else
                                        <span class="text-muted text-sm">{{ __('professionals.hours.closed') }}</span>
                                    @endif
                                </x-catalog.form-row>
                            @endforeach
                        </div>
                    @endunless
                </div>

                <div>
                    <p class="text-strong text-sm font-semibold">{{ __('professionals.services.label') }}</p>
                    <p class="text-muted mb-2 text-xs">{{ __('professionals.services.hint') }}</p>

                    <div class="flex flex-col gap-3">
                        @foreach ($pieces->bookableServices as $service)
                            <div wire:key="ps-{{ $service->id }}">
                                <x-ui.switch name="service-{{ $service->id }}" :label="$service->name" wire:model.live="professional.data.services.{{ $service->id }}.attends" />

                                @if ($professional->data->services[$service->id]['attends'] ?? false)
                                    <x-catalog.form-row>
                                        <x-inputsform.input span="short" name="services.{{ $service->id }}.duration_minutes" :label="__('professionals.fields.duration')" :placeholder="$service->duration_minutes !== null ? (string) $service->duration_minutes : ''" class="font-mono" inputmode="numeric" wire:model="professional.data.services.{{ $service->id }}.duration_minutes" />
                                        <x-inputsform.input span="short" name="services.{{ $service->id }}.price" :label="__('professionals.fields.price')" class="font-mono" inputmode="numeric" wire:model="professional.data.services.{{ $service->id }}.price" />
                                    </x-catalog.form-row>
                                    <x-catalog.form-row>
                                        <x-inputsform.combobox
                                            span="text"
                                            name="services.{{ $service->id }}.price_type"
                                            :label="__('professionals.fields.price_type')"
                                            wire:model="professional.data.services.{{ $service->id }}.price_type"
                                            :value="$professional->data->services[$service->id]['price_type'] ?? ''"
                                            :options="[
                                                '' => __('professionals.services.same'),
                                                'fixed' => __('client.services.price_fixed'),
                                                'from' => __('client.services.price_from'),
                                                'free' => __('client.services.price_free'),
                                                'talk' => __('client.services.price_talk'),
                                            ]"
                                        />
                                        <x-inputsform.input span="short" name="services.{{ $service->id }}.deposit" :label="__('professionals.fields.deposit')" class="font-mono" inputmode="numeric" wire:model="professional.data.services.{{ $service->id }}.deposit" />
                                    </x-catalog.form-row>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-slot:footer>
                @if ($professional->editingId !== null)
                    <x-ui.icon-button
                        icon="trash-2"
                        variant="ghost"
                        :label="__('professionals.sheet.delete')"
                        x-data
                        x-on:click="dialog.confirm({
                            title: {{ Js::from(__('professionals.sheet.delete_title', ['name' => $professional->data->name])) }},
                            message: {{ Js::from(__('professionals.sheet.delete_message')) }},
                            accept: {{ Js::from(__('professionals.sheet.delete')) }},
                            type: 'danger',
                        }).then((yes) => yes && $wire.remove())"
                    />
                @endif
                <x-ui.button variant="danger" wire:click="$set('sheetOpen', false)">{{ __('professionals.sheet.cancel') }}</x-ui.button>
                <x-ui.button class="ml-auto" icon="check" wire:click="save">{{ __('professionals.sheet.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
    @endif
</div>
