@php
    $brand = \App\Models\Company::brand();
    $contact = \App\Models\Company::contact();
    $days = (int) config('atendia.account_restore_days');
@endphp

<x-guest-layout>
    <x-slot:title>{{ __('privacy.title', ['brand' => $brand]) }}</x-slot:title>

    <div class="flex flex-col gap-6" data-testid="privacy-policy">
        <header class="flex flex-col gap-2">
            <p class="eyebrow">{{ $brand }}</p>
            <h1
                class="text-strong font-display"
                style="font-weight: 800; font-size: var(--text-3xl); line-height: 1.15"
            >
                {{ __('privacy.heading') }}
            </h1>
            <p class="text-body">{{ __('privacy.lead', ['brand' => $brand]) }}</p>
            <p class="text-muted text-sm">{{ __('privacy.updated') }}</p>
        </header>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.who.title') }}
            </h2>
            <p class="text-body">
                {{ __('privacy.who.body', ['legal' => $contact['legal_name'], 'brand' => $brand]) }}
            </p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.collect.title') }}
            </h2>
            <ul class="text-body flex list-disc flex-col gap-2 pl-5">
                <li>{{ __('privacy.collect.account') }}</li>
                <li>{{ __('privacy.collect.business') }}</li>
                <li>{{ __('privacy.collect.messages') }}</li>
                <li>{{ __('privacy.collect.photos') }}</li>
                <li>{{ __('privacy.collect.payments') }}</li>
                <li>{{ __('privacy.collect.device') }}</li>
            </ul>
            <p class="text-body">{{ __('privacy.collect.none') }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.use.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.use.body') }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.share.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.share.body') }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.security.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.security.body', ['brand' => $brand]) }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.keep.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.keep.body', ['days' => $days]) }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.rights.title') }}
            </h2>
            <p class="text-body">
                {{ __('privacy.rights.body') }}
                <a href="{{ route('account.deletion') }}" class="text-brand">{{ __('privacy.rights.link') }}</a>.
            </p>
            @if ($contact['email'] !== null)
                <p class="text-body">
                    {{ __('privacy.rights.mail') }}
                    <a href="mailto:{{ $contact['email'] }}" class="text-brand font-mono">{{ $contact['email'] }}</a>.
                </p>
            @endif
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.minors.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.minors.body', ['brand' => $brand]) }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('privacy.changes.title') }}
            </h2>
            <p class="text-body">{{ __('privacy.changes.body') }}</p>
        </section>

        <p class="text-subtle text-sm">{{ $contact['legal_name'] }}</p>
    </div>
</x-guest-layout>
