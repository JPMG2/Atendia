@props(['user'])

@php
    // Read-only glance, Google-checkup style: every value comes from domain
    // methods. The ring counts what can actually vary per account.
    $lastLogin = $user->recentLoginActivity(1)->first();
    $emailOk = $user->hasVerifiedEmail();
    $devicesOk = $user->recentDevices()->every(fn ($device) => ! $user->locationIsUnusual($device->location));

    $score = count(array_filter([$emailOk, true, $devicesOk, $lastLogin !== null]));
    $total = 4;
    $percent = (int) round(($score / $total) * 100);
@endphp

<x-ui.card class="p-5 sm:p-6">
    <div class="flex items-center gap-4">
        <div
            class="relative h-11 w-11 flex-shrink-0"
            role="img"
            aria-label="{{ __('profile.checkup.score', ['score' => $score, 'total' => $total]) }}"
        >
            <svg viewBox="0 0 44 44" class="h-11 w-11 -rotate-90">
                <circle cx="22" cy="22" r="19" fill="none" stroke="var(--border-subtle)" stroke-width="4" />
                <circle
                    cx="22"
                    cy="22"
                    r="19"
                    fill="none"
                    stroke="var(--brand)"
                    stroke-width="4"
                    stroke-linecap="round"
                    pathLength="100"
                    stroke-dasharray="{{ $percent }} 100"
                />
            </svg>
            <span
                class="text-strong absolute inset-0 flex items-center justify-center font-mono font-semibold"
                style="font-size: var(--text-2xs)"
            >{{ $score }}/{{ $total }}</span>
        </div>
        <div>
            <h2 class="text-strong font-display" style="font-size: var(--text-lg); font-weight: 700">
                {{ __('profile.checkup.title') }}
            </h2>
            <p class="text-muted" style="font-size: var(--text-sm)">
                {{ __('profile.checkup.score', ['score' => $score, 'total' => $total]) }}
            </p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex items-center gap-3">
            <span
                class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                style="background: {{ $emailOk ? 'var(--brand-soft)' : 'var(--warning-soft)' }}; color: {{ $emailOk ? 'var(--brand)' : 'var(--warning)' }}"
            >
                <x-icon name="mail" :size="18" />
            </span>
            <div class="min-w-0">
                <p class="text-strong font-semibold" style="font-size: var(--text-sm)">
                    {{ $emailOk ? __('profile.checkup.email_ok') : __('profile.checkup.email_pending') }}
                </p>
                <p class="text-muted" style="font-size: var(--text-xs)">
                    {{ $emailOk ? __('profile.checkup.email_ok_detail') : __('profile.checkup.email_pending_detail') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span
                class="bg-brand-soft inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                style="color: var(--brand)"
            >
                <x-icon name="shield-check" :size="18" />
            </span>
            <div class="min-w-0">
                <p class="text-strong font-semibold" style="font-size: var(--text-sm)">
                    {{ __('profile.checkup.password') }}
                </p>
                <p class="text-muted" style="font-size: var(--text-xs)">{{ __('profile.checkup.password_detail') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span
                class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                style="background: {{ $devicesOk ? 'var(--brand-soft)' : 'var(--warning-soft)' }}; color: {{ $devicesOk ? 'var(--brand)' : 'var(--warning)' }}"
            >
                <x-icon name="monitor" :size="18" />
            </span>
            <div class="min-w-0">
                <p class="text-strong font-semibold" style="font-size: var(--text-sm)">
                    {{ __('profile.checkup.devices') }}
                </p>
                <p class="text-muted" style="font-size: var(--text-xs)">
                    {{
                        $devicesOk
                        ? __('profile.checkup.devices_detail', ['count' => $user->recentDevices()->count()])
                        : __('profile.checkup.devices_warn_detail')
                    }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span
                class="bg-brand-soft inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                style="color: var(--brand)"
            >
                <x-icon name="clock" :size="18" />
            </span>
            <div class="min-w-0">
                <p class="text-strong font-semibold" style="font-size: var(--text-sm)">
                    {{ __('profile.checkup.last_login') }}
                </p>
                <p class="text-muted" style="font-size: var(--text-xs)">
                    @if ($lastLogin)
                        {{ $lastLogin->created_at->diffForHumans() }}
                        @if ($lastLogin->location)
                            · {{ $lastLogin->location }}
                        @endif
                    @else
                        {{ __('profile.checkup.last_login_empty') }}
                    @endif
                </p>
            </div>
        </div>
    </div>
</x-ui.card>
