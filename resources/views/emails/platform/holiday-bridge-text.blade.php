@php
    $when = collect($dates)->map(fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y'))->implode(', ');
@endphp
{{ trans_choice('catalog.country_holiday.bridge.mail.subject', count($dates)) }}

{{ __('catalog.country_holiday.bridge.mail.intro', ['name' => $business->name]) }}

{{ trans_choice('catalog.country_holiday.bridge.mail.body', count($dates), ['dates' => $when, 'country' => $business->country?->name ?? '']) }}
{{ __('catalog.country_holiday.bridge.mail.cta') }}: {{ route('dashboard') }}

{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
