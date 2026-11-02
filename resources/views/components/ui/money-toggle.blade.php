@props([
    'remember', // the screen's own key: each screen remembers what this person last picked there
])

{{-- Dollars first for anyone who never picked: the toggle only changes what this
viewer reads, never what is charged. It drives the `ves` of the parent x-data. --}}
<div
    x-data="moneyToggle(@js($remember))"
    {{ $attributes->merge(['class' => 'money-toggle']) }}
    role="group"
    aria-label="{{ __('exchange_rate.toggle.label') }}"
>
    <span>{{ __('exchange_rate.toggle.label') }}</span>
    <span class="money-toggle-seg">
        <button type="button" x-on:click="pick(false)" x-bind:aria-pressed="(! ves).toString()" aria-pressed="true">
            {{ __('exchange_rate.toggle.usd') }}
        </button>
        <button type="button" x-on:click="pick(true)" x-bind:aria-pressed="ves.toString()" aria-pressed="false">
            {{ __('exchange_rate.toggle.ves') }}
        </button>
    </span>
</div>
