@php
    $brand = \App\Models\Company::brand();
    $contact = \App\Models\Company::contact();
    $days = (int) config('atendia.account_restore_days');
@endphp

<x-guest-layout>
    <x-slot:title>{{ __('deletion.title', ['brand' => $brand]) }}</x-slot:title>

    <div class="flex flex-col gap-6" data-testid="account-deletion">
        <header class="flex flex-col gap-2">
            <p class="eyebrow">{{ $brand }}</p>
            <h1
                class="text-strong font-display"
                style="font-weight: 800; font-size: var(--text-3xl); line-height: 1.15"
            >
                {{ __('deletion.heading') }}
            </h1>
            <p class="text-body">{{ __('deletion.lead', ['brand' => $brand]) }}</p>
        </header>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('deletion.how.title') }}
            </h2>
            <ol class="text-body flex list-decimal flex-col gap-2 pl-5">
                <li>{{ __('deletion.how.app', ['brand' => $brand]) }}</li>
                <li>{{ __('deletion.how.web', ['brand' => $brand]) }}</li>
                @if ($contact['email'] !== null)
                    <li>
                        {{ __('deletion.how.mail') }}
                        <a
                            href="mailto:{{ $contact['email'] }}"
                            class="text-brand font-mono"
                        >{{ $contact['email'] }}</a>
                    </li>
                @endif
            </ol>
            <p class="text-muted text-sm">{{ __('deletion.how.owner') }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('deletion.when.title') }}
            </h2>
            <p class="text-body">{{ __('deletion.when.body', ['days' => $days]) }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('deletion.erased.title') }}
            </h2>
            <p class="text-body">{{ __('deletion.erased.body') }}</p>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                {{ __('deletion.kept.title') }}
            </h2>
            <p class="text-body">{{ __('deletion.kept.payments') }}</p>
            <p class="text-body">{{ __('deletion.kept.suspended') }}</p>
        </section>

        <p class="text-subtle text-sm">{{ $contact['legal_name'] }}</p>
    </div>
</x-guest-layout>
