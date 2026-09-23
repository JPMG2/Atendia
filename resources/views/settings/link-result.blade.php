@php
    // One page for every account link outcome: state → icon, tone and the
    // next step, so the three signed links answer in the same voice.
    $states = [
        'confirmed' => ['icon' => 'circle-check', 'ok' => true, 'cta' => 'to_settings', 'href' => route('settings')],
        'verified' => ['icon' => 'circle-check', 'ok' => true, 'cta' => 'to_settings', 'href' => route('settings')],
        'invalid' => ['icon' => 'triangle-alert', 'ok' => false, 'cta' => 'to_settings', 'href' => route('settings')],
        'cancelled' => ['icon' => 'shield-check', 'ok' => true, 'cta' => 'change_password', 'href' => route('password.request')],
        'restored' => ['icon' => 'circle-check', 'ok' => true, 'cta' => 'to_login', 'href' => route('login')],
        'restore_expired' => ['icon' => 'triangle-alert', 'ok' => false, 'cta' => 'to_login', 'href' => route('login')],
    ];
    $current = $states[$state] ?? $states['invalid'];
    $key = isset($states[$state]) ? $state : 'invalid';
@endphp

<x-guest-layout>
    <div class="text-center">
        <span
            class="inline-flex h-12 w-12 items-center justify-center rounded-full"
            style="background: {{ $current['ok'] ? 'var(--brand-soft)' : 'var(--warning-soft)' }}; color: {{ $current['ok'] ? 'var(--brand)' : 'var(--warning)' }}"
        >
            <x-icon :name="$current['icon']" :size="24" />
        </span>

        <h2
            class="text-strong mt-4 font-display"
            style="font-weight: 800; font-size: var(--text-2xl); letter-spacing: -0.02em"
        >
            {{ __("settings.links.{$key}_title") }}
        </h2>

        <p class="text-muted mt-2" style="font-size: var(--text-base)">
            {{ __("settings.links.{$key}_body", ['email' => $email ?? '']) }}
        </p>

        <div class="mt-6 flex flex-col items-center gap-3">
            <x-ui.button variant="primary" :href="$current['href']">
                {{ __('settings.links.'.$current['cta']) }}
            </x-ui.button>
        </div>
    </div>
</x-guest-layout>
