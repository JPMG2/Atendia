{{-- Opens "Ask AtendIa" with the question about THIS block already asked (the GA "ask from the chart" pattern). --}}
@props([
    'question',
])

<button
    type="button"
    class="stats-ask"
    title="{{ __('ask.title') }}"
    x-on:click="$dispatch('ask-atendia', { question: @js($question) })"
>
    <x-icon name="sparkles" :size="14" />
    {{ __('ask.chart.button') }}
</button>
