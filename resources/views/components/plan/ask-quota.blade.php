{{-- The "Ask AtendIa" quota, one piece for both homes: the branded card of
"Mi plan" and the compact counter under the assistant's input. Same bar and
states as every usage meter (warning from 80%, alarm at the cap). --}}
@props([
    'used',
    'cap',
    'renewsOn',
    'size' => 'card', // card | compact
])

@php
    $left = max(0, $cap - $used);
    // The bar fills with what was USED, like every meter of the panel (her call).
    $percent = $cap > 0 ? min(100, (int) round($used / $cap * 100)) : 0;
    $state = \App\Classes\Main\Plan::usageState($used, $cap);
@endphp

@if ($size === 'compact')
    <div class="ask-quota-mini plan-meter" data-state="{{ $state }}">
        <x-icon name="sparkles" :size="14" />
        <div class="plan-meter-track"><div class="plan-meter-fill" style="width: {{ $percent }}%"></div></div>
        <span class="ask-quota-mini-figures">
            {{ __('ask.quota.left') }} <b>{{ $left }}</b> {{ __('ask.quota.of_short', ['cap' => $cap]) }}
        </span>
    </div>
@else
    <div class="ask-quota plan-meter" data-state="{{ $state }}">
        <span class="ask-hello-avatar"><x-icon name="sparkles" :size="22" /></span>
        <div class="min-w-0 flex-1">
            <p class="ask-quota-title">{{ __('ask.title') }}</p>
            <p class="ask-quota-left">
                <span>{{ __('ask.quota.left') }}</span>
                <b class="ask-quota-number">{{ $left }}</b>
                <span>{{ __('ask.quota.of', ['cap' => $cap]) }}</span>
            </p>
            <div class="ask-quota-bar">
                <div class="plan-meter-track"><div class="plan-meter-fill" style="width: {{ $percent }}%"></div></div>
                <span class="ask-quota-used">{{ trans_choice('ask.quota.used', $used, ['count' => $used]) }}</span>
            </div>
            <p class="ask-quota-renews">{{ __('ask.quota.renews', ['date' => $renewsOn]) }}</p>
        </div>
    </div>
@endif
