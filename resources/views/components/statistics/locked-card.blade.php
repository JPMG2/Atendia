{{-- A locked block stays VISIBLE: the padlock plus the plan that opens it is
the silent seller (the blessed Vercel/Notion pattern), never a hidden hole. --}}
@props([
    'title',
    'plan',
])

<x-ui.card class="stats-locked p-6">
    <div class="stats-locked-head">
        <x-icon name="lock" :size="16" />
        <h2>{{ $title }}</h2>
    </div>
    <p class="stats-locked-hint">{{ __('statistics.locked_in', ['plan' => __('plan.names.'.$plan)]) }}</p>
    <a href="{{ route('my-plan') }}" wire:navigate class="stats-locked-link"> {{ __('statistics.see_plans') }} </a>
</x-ui.card>
