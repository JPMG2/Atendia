<?php

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\LostPhonePauseForm;
use App\Livewire\Forms\Business\LostPhoneResumeForm;
use App\Models\Business;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Me robaron el teléfono": a pause, not a goodbye. Billing goes on; what
 * stops is everything that could reach or speak through a phone in someone
 * else's hand. Pausing sets a new password; lifting it asks that one, then a fresh QR.
 */
new class extends Component
{
    use HasNotifications;

    public LostPhonePauseForm $pauseForm;

    public LostPhoneResumeForm $resumeForm;

    public function mount(): void
    {
        $this->pauseForm->setup();
        $this->resumeForm->setup();
    }

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    public function pause(): void
    {
        $notification = $this->pauseForm->save();
        $this->dispatchNotification($notification);

        // The whole screen changes shape (no QR, the banner on top): a fresh visit draws it.
        if ($notification->type !== NotificationType::Error) {
            $this->redirectRoute('whatsapp', navigate: true);
        }
    }

    public function resume(): void
    {
        $notification = $this->resumeForm->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->redirectRoute('whatsapp', navigate: true);
        }
    }
};
?>

<div id="perdi-el-telefono" class="scroll-mt-24" data-testid="lost-phone">
    @if ($this->business?->isPausedForLostPhone())
        <x-ui.card class="p-5">
            <div class="flex items-start gap-4">
                <span
                    class="flex size-12 flex-none items-center justify-center rounded-2xl"
                    style="background: var(--warning-soft); color: var(--warning)"
                >
                    <x-icon name="shield-alert" :size="24" />
                </span>
                <div class="min-w-0 flex-1 space-y-3">
                    <span class="status-tag is-warning">
                        <span class="dot"></span>
                        {{ __('whatsapp.lost_phone.paused_tag') }}
                    </span>
                    <h2 class="font-display text-strong text-lg">{{ __('whatsapp.lost_phone.paused_title') }}</h2>
                    <p class="text-muted text-sm">
                        {{ __('whatsapp.lost_phone.paused_since') }}
                        <span class="font-mono">{{ $this->business->lost_phone_paused_at->inBusinessTime()->format('d/m/Y H:i') }}</span>
                        @if ($this->business->lostPhonePausedBy)
                            · {{ __('whatsapp.lost_phone.paused_by', ['name' => $this->business->lostPhonePausedBy->name]) }}
                        @endif
                    </p>

                    <ul class="space-y-1.5">
                        @foreach (__('whatsapp.lost_phone.effects') as $effect)
                            <li class="text-body flex items-start gap-2 text-sm">
                                <span class="mt-0.5 flex-none" style="color: var(--success)"><x-icon name="check" :size="16" /></span>
                                {{ $effect }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-muted text-sm">{{ __('whatsapp.lost_phone.billing') }}</p>

                    <div class="bd-subtle space-y-2 border-t pt-4" data-testid="lost-phone-cut">
                        <h3 class="font-display text-strong text-base">{{ __('whatsapp.lost_phone.cut_title') }}</h3>
                        @php($recentDays = \App\Actions\Business\PauseForLostPhone::RECENT_DAYS)
                        @if (empty($this->business->lost_phone_cut_sessions))
                            <p class="text-muted text-sm">{{ __('whatsapp.lost_phone.cut_none', ['days' => $recentDays]) }}</p>
                        @else
                            <p class="text-muted text-sm">{{ __('whatsapp.lost_phone.cut_hint', ['days' => $recentDays]) }}</p>
                            <ul class="bd-subtle divide-y divide-[color:var(--border-subtle)] rounded-xl border">
                                @foreach ($this->business->lost_phone_cut_sessions as $cut)
                                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm" wire:key="cut-{{ $loop->index }}">
                                        <span class="text-muted flex-none"><x-icon :name="($cut['mobile'] ?? $cut['app']) ? 'smartphone' : 'monitor'" :size="16" /></span>
                                        <span class="text-strong font-semibold">{{ $cut['name'] }}</span>
                                        <span class="text-body">
                                            {{ $cut['device'] }}@if ($cut['app']) · {{ __('whatsapp.lost_phone.cut_app') }}@endif
                                        </span>
                                        @if ($cut['current'] ?? false)
                                            <span class="status-tag is-info">{{ __('whatsapp.lost_phone.cut_current') }}</span>
                                        @endif
                                        @if ($cut['last_login_at'])
                                            <span class="text-muted ml-auto">
                                                {{ __('whatsapp.lost_phone.cut_last') }}
                                                <span class="font-mono">{{ \Carbon\CarbonImmutable::parse($cut['last_login_at'])->inBusinessTime()->format('d/m/Y H:i') }}</span>
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="bd-subtle space-y-3 border-t pt-4">
                        <h3 class="font-display text-strong text-base">{{ __('whatsapp.lost_phone.resume_title') }}</h3>
                        <p class="text-body text-sm">{{ __('whatsapp.lost_phone.resume_body') }}</p>
                        <form wire:submit="resume" class="space-y-3">
                            <x-catalog.form-row>
                                <x-inputsform.input
                                    span="text"
                                    type="password"
                                    name="current_password"
                                    wire:model="resumeForm.current_password"
                                    :label="__('settings.current_password')"
                                    autocomplete="current-password"
                                    required
                                />
                            </x-catalog.form-row>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="shield-check">
                                {{ __('whatsapp.lost_phone.resume_button') }}
                            </x-ui.button>
                        </form>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @elseif ($this->business !== null)
        <x-ui.card class="p-5">
            <div class="flex items-start gap-4">
                <span class="bg-sunken text-muted flex size-12 flex-none items-center justify-center rounded-2xl">
                    <x-icon name="smartphone" :size="24" />
                </span>
                <div class="min-w-0 flex-1 space-y-3">
                    <h2 class="font-display text-strong text-lg">{{ __('whatsapp.lost_phone.title') }}</h2>
                    <p class="text-body text-sm">{{ __('whatsapp.lost_phone.body') }}</p>
                    <ul class="space-y-1.5">
                        @foreach (__('whatsapp.lost_phone.effects') as $effect)
                            <li class="text-body flex items-start gap-2 text-sm">
                                <span class="text-muted mt-0.5 flex-none"><x-icon name="lock" :size="16" /></span>
                                {{ $effect }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-muted text-sm">{{ __('whatsapp.lost_phone.billing') }}</p>

                    <div class="bd-subtle space-y-3 border-t pt-4" x-data="lostPhonePause">
                        <p class="text-body text-sm">{{ __('whatsapp.lost_phone.password_hint') }}</p>
                        <x-catalog.form-row>
                            <x-inputsform.input
                                span="text"
                                type="password"
                                name="current_password"
                                alpine-error="current_password"
                                wire:model="pauseForm.current_password"
                                :label="__('settings.current_password')"
                                autocomplete="current-password"
                                required
                            />
                            <x-inputsform.input
                                span="text"
                                type="password"
                                name="password"
                                alpine-error="password"
                                wire:model="pauseForm.password"
                                :label="__('settings.password.new')"
                                autocomplete="new-password"
                                required
                            />
                            <x-inputsform.input
                                span="text"
                                type="password"
                                name="password_confirmation"
                                alpine-error="password_confirmation"
                                wire:model="pauseForm.password_confirmation"
                                :label="__('settings.password.confirm')"
                                autocomplete="new-password"
                                required
                            />
                        </x-catalog.form-row>
                        <x-ui.button variant="secondary" size="sm" icon="shield-alert" x-on:click="submit()">
                            {{ __('whatsapp.lost_phone.button') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>

@script
    <script>
        // Fields first, then the system dialog: reversible with the new password and a QR, so a warning.
        Alpine.data('lostPhonePause', () => ({
            errors: {},
            path: 'pauseForm',

            async submit() {
                const values = {
                    current_password: this.$wire.get(`${this.path}.current_password`) ?? '',
                    password: this.$wire.get(`${this.path}.password`) ?? '',
                    password_confirmation: this.$wire.get(`${this.path}.password_confirmation`) ?? '',
                };

                this.errors = validate(values, {
                    current_password: ['required'],
                    password: ['required', ['minLength', 8], 'hasUpper', 'hasNumber', 'hasSymbol'],
                    password_confirmation: ['required', ['same', values.password]],
                });

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                if (
                    !(await dialog.confirm({
                        title: @js(__('whatsapp.lost_phone.confirm_title')),
                        message: @js(__('whatsapp.lost_phone.confirm_message')),
                        accept: @js(__('whatsapp.lost_phone.confirm_accept')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.pause();
            },
        }));
    </script>
@endscript
