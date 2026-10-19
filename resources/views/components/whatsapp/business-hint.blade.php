@props([
    'compact' => false, // one line with no steps: the reminder shown once the number is linked
])

{{-- A quiet line and not a box: it shares the screen with two warnings already.
It never goes away on its own: a Business number with the app's greeting on
answers twice for as long as the number stays linked. --}}
<div x-data="{ open: false }" class="text-muted text-sm" data-testid="business-app-hint">
    <p class="flex items-start gap-2">
        <span class="text-subtle mt-0.5 flex-none"><x-icon name="info" :size="16" /></span>
        <span>
            {{ $compact ? __('whatsapp.business_app.reminder') : __('whatsapp.business_app.line') }}
            @unless ($compact)
                <button
                    type="button"
                    class="text-brand whitespace-nowrap font-semibold hover:underline"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open"
                    data-testid="business-app-how"
                >
                    <span x-show="! open">{{ __('whatsapp.business_app.how') }}</span>
                    <span x-show="open" x-cloak>{{ __('whatsapp.business_app.hide') }}</span>
                </button>
            @endunless
        </span>
    </p>

    @unless ($compact)
        <ol x-show="open" x-cloak class="text-body mt-2 list-decimal space-y-1 ps-9">
            @foreach (__('whatsapp.business_app.steps') as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    @endunless
</div>
