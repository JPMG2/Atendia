@props([
    'reach', // Company::reach(): the hours summary and whether the line is open right now
])

{{--
    The sales line's hours and its open-now dot, read from the Company row (and
    the season in force). Shared by the footer and the sales chat so the two
    can never tell a visitor different hours.
--}}
@if ($reach['hours'])
    <span class="text-muted inline-flex items-start gap-2" style="font-size: var(--text-sm)">
        <x-icon name="clock" :size="15" class="mt-0.5 flex-none" />
        {{ $reach['hours'] }}
    </span>
@endif

{{-- Said only when a week was declared: a missing schedule is not
a closed one, and a dot that always says "closed" is noise. --}}
@if ($reach['open'] !== null)
    <span class="text-muted inline-flex items-center gap-2" style="font-size: var(--text-sm)" role="status">
        <span
            aria-hidden="true"
            class="inline-block rounded-full"
            style="width: 8px; height: 8px; background: {{ $reach['open'] ? 'var(--success)' : 'var(--text-subtle)' }}"
        ></span>
        {{ $reach['open'] ? __('landing.footer.open_now') : __('landing.footer.closed_now') }}
    </span>
@endif
