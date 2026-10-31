@php
    $when = collect($dates)->map(fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y'))->implode(', ');
    $words = \App\Mail\HolidayBridgeNotice::wordsFor($bridge);
@endphp

<x-email.layout :preheader="__('catalog.country_holiday.bridge.mail.preheader')">
    <x-email.notice
        :eyebrow="__('catalog.country_holiday.bridge.mail.eyebrow')"
        :title="trans_choice($words.'.subject', count($dates))"
        :intro="__('catalog.country_holiday.bridge.mail.intro', ['name' => $business->name])"
        :body="trans_choice($words.'.body', count($dates), ['dates' => $when, 'country' => $business->country?->name ?? ''])"
        :primary-url="route('dashboard')"
        :primary-label="__('catalog.country_holiday.bridge.mail.cta')"
        :closing="__('catalog.country_holiday.bridge.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
