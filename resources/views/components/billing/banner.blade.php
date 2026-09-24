@use('App\Classes\Main\Client')

@php
    // One reminder for the whole client panel, read from the Billing piece:
    // the same numbers the mail, the WhatsApp and "Mis pagos" show.
    $reminder = auth()->user() ? Client::for(auth()->user())->billing?->reminder : null;
    $date = auth()->user()?->business?->subscription?->periodEndsAt()?->inBusinessTime()->format('d/m');
@endphp

@if ($reminder !== null)
    <div
        @class([
            'pay-banner',
            'is-danger' => in_array($reminder['stage'], ['overdue', 'paused'], true),
            'is-info' => $reminder['stage'] === 'verifying',
        ])
        role="status"
    >
        <x-icon :name="$reminder['stage'] === 'verifying' ? 'circle-check' : 'triangle-alert'" :size="20" />
        <p class="pay-banner-text">
            @switch ($reminder['stage'])
                @case ('upcoming')
                    <b>{{ trans_choice('billing.banner.upcoming', $reminder['days']) }}</b>
                    {{ __('billing.banner.upcoming_body', ['date' => $date]) }}
                    @break
                @case ('overdue')
                    <b>{{ __('billing.banner.overdue') }}</b>
                    {{ trans_choice('billing.banner.overdue_body', $reminder['days']) }}
                    @break
                @case ('paused')
                    <b>{{ __('billing.banner.paused') }}</b>
                    {{ __('billing.banner.paused_body') }}
                    @break
                @default
                    <b>{{ __('billing.banner.verifying') }}</b>
                    {{ __('billing.banner.verifying_body') }}
            @endswitch
        </p>
        @unless (request()->routeIs('my-payments') || $reminder['stage'] === 'verifying')
            <x-ui.button variant="primary" size="sm" :href="route('my-payments')">
                {{ __('billing.banner.cta') }}</x-ui.button>
        @endunless
    </div>
@endif
