{{-- Asks before Google Analytics loads. It is drawn only while a measurement id
is set; analytics-consent.js shows it, hides it and loads the script. --}}
@php($measurementId = config('atendia.analytics.measurement_id'))

@if (filled($measurementId))
    <div
        class="cookie-consent flex flex-col gap-3"
        data-consent="{{ $measurementId }}"
        data-testid="cookie-consent"
        role="dialog"
        aria-labelledby="cookie-consent-title"
        hidden
    >
        <h2
            id="cookie-consent-title"
            class="text-strong font-display"
            style="font-weight: 700; font-size: var(--text-base)"
        >
            {{ __('landing.cookies.title') }}
        </h2>
        <p class="text-body" style="font-size: var(--text-sm)">
            {{ __('landing.cookies.body') }}
            <a href="{{ route('privacy') }}" class="text-brand underline">{{ __('landing.cookies.more') }}</a>
        </p>
        <div class="flex flex-wrap gap-3">
            <x-ui.button variant="secondary" size="md" data-consent-reject>
                {{ __('landing.cookies.reject') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" data-consent-accept>
                {{ __('landing.cookies.accept') }}
            </x-ui.button>
        </div>
    </div>
@endif
