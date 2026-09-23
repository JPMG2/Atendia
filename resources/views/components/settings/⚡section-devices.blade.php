<?php

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\LoginDevice;
use App\Traits\HasNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Connected devices" card of the settings page: every browser the account
 * signed in from, with a remote sign-out. Revoking becomes real in the
 * LogoutRevokedDevice middleware, which kills that session's next request.
 */
new class extends Component
{
    use HasNotifications;

    public bool $confirmingAll = false;

    public string $password = '';

    /** @return Collection<int, LoginDevice> */
    #[Computed]
    public function devices(): Collection
    {
        return auth()->user()->recentDevices();
    }

    #[Computed]
    public function currentFingerprint(): string
    {
        return LoginDevice::fingerprintFor(request()->userAgent());
    }

    public function revoke(int $deviceId): void
    {
        $device = $this->devices->firstWhere('id', $deviceId);

        if ($device === null) {
            return;
        }

        // Guarded here and not only by hiding the button: signing out the
        // device in use would strand this very screen mid-request.
        if ($device->fingerprint === $this->currentFingerprint) {
            $this->dispatchNotification(new NotificationDto(__('profile.devices.current_refused'), NotificationType::Warning));

            return;
        }

        $device->delete();
        unset($this->devices);

        $this->dispatchNotification(new NotificationDto(__('profile.devices.revoked'), NotificationType::Success));
    }

    public function cancelRevokeAll(): void
    {
        $this->reset('password', 'confirmingAll');
        $this->resetErrorBag();
    }

    /**
     * GitHub-style "close every other session": the password re-confirms it
     * is really the owner, throttled so this button can never become a quiet
     * oracle to brute-force the account's password from inside.
     */
    public function revokeOthers(): void
    {
        $user = auth()->user();
        $key = 'revoke-devices:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('password', __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        if (! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($key);
            $this->addError('password', __('validation.current_password'));

            return;
        }

        RateLimiter::clear($key);
        $user->revokeOtherDevices($this->currentFingerprint);
        $this->cancelRevokeAll();
        unset($this->devices);

        $this->dispatchNotification(new NotificationDto(__('profile.devices.revoke_all_done'), NotificationType::Success));
    }
};
?>

<x-ui.card id="dispositivos" class="bp-card" x-data="profileDevices">
    <div class="bp-card-head">
        <h2>{{ __('profile.devices.title') }}</h2>
        @if ($this->devices->count() > 1 && ! $confirmingAll)
            <x-ui.button class="ml-auto" variant="secondary" size="sm" icon="log-out" wire:click="$set('confirmingAll', true)">
                {{ __('profile.devices.revoke_all') }}
            </x-ui.button>
        @endif
    </div>
    <p class="bp-card-sub">{{ __('profile.devices.sub') }}</p>

    @if ($confirmingAll)
        <div class="bg-sunken mt-4 rounded-xl p-4">
            <p class="text-body" style="font-size: var(--text-sm)">{{ __('profile.devices.revoke_all_hint') }}</p>
            <div class="mt-3 flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <x-ui.input
                        name="password"
                        type="password"
                        size="sm"
                        wire:model="password"
                        :placeholder="__('profile.devices.confirm_password')"
                        autocomplete="current-password"
                    />
                </div>
                <x-ui.button variant="primary" size="sm" wire:click="revokeOthers">
                    {{ __('profile.devices.revoke_all_confirm') }}
                </x-ui.button>
                <x-ui.button variant="danger" size="sm" wire:click="cancelRevokeAll">
                    {{ __('profile.devices.cancel') }}
                </x-ui.button>
            </div>
        </div>
    @endif

    <ul class="mt-4">
        @forelse ($this->devices as $device)
            <li
                wire:key="device-{{ $device->id }}"
                class="bd-subtle flex flex-wrap items-center gap-3 border-b py-2.5 last:border-b-0"
            >
                <x-icon
                    :name="$device->isMobile() ? 'smartphone' : 'monitor'"
                    :size="18"
                    style="color: var(--brand)"
                />

                <div class="min-w-0 flex-1">
                    <p class="text-strong flex flex-wrap items-center gap-2 font-semibold" style="font-size: var(--text-sm)">
                        {{ $device->label() }}
                        @if ($device->fingerprint === $this->currentFingerprint)
                            <x-ui.badge variant="brand" :dot="true">{{ __('profile.devices.current') }}</x-ui.badge>
                        @endif
                    </p>
                    <p class="text-muted mt-0.5 flex flex-wrap items-center gap-1" style="font-size: var(--text-xs)">
                        <span class="font-mono">{{ $device->ip }}</span>
                        @if ($device->location)
                            · {{ $device->location }}
                            {{-- GitHub-style: a place the account visited only once
                            deserves a second look. --}}
                            @if (auth()->user()->locationIsUnusual($device->location))
                                <span class="status-tag is-warning">{{ __('profile.devices.unusual') }}</span>
                            @endif
                        @endif
                        · {{ __('profile.devices.last_seen') }}: {{ $device->last_login_at->diffForHumans() }}
                    </p>
                </div>

                @if ($device->fingerprint !== $this->currentFingerprint)
                    <x-ui.button
                        variant="danger"
                        size="sm"
                        icon="log-out"
                        x-on:click="revoke({{ $device->id }})"
                    >
                        {{ __('profile.devices.revoke') }}
                    </x-ui.button>
                @endif
            </li>
        @empty
            <li class="text-muted" style="font-size: var(--text-sm)">{{ __('profile.devices.empty') }}</li>
        @endforelse
    </ul>
</x-ui.card>

@script
    <script>
        Alpine.data('profileDevices', () => ({
            async revoke(id) {
                if (
                    !(await dialog.confirm({
                        title: @js(__('profile.devices.confirm_title')),
                        message: @js(__('profile.devices.confirm_message')),
                        accept: @js(__('profile.devices.confirm_accept')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.revoke(id);
            },
        }));
    </script>
@endscript
