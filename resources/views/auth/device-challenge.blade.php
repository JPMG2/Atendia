<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('security.challenge.title') }}
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">{{ __('security.challenge.sub') }}</p>
        @if ($maskedEmail)
            <p class="text-body mt-1.5" style="font-size: var(--text-sm)">
                {{ __('security.challenge.sent_to') }}
                <span class="font-mono font-semibold">{{ $maskedEmail }}</span>
            </p>
        @endif
    </div>

    {{-- The resend confirmation, Breeze-status style. --}}
    @if (session('status'))
        <x-ui.alert variant="success" icon="message-circle" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    {{-- Bare x-data so Alpine adopts the tree: the auto-submit below needs it. --}}
    <form method="POST" action="{{ route('device.challenge.verify') }}" class="flex flex-col gap-5" x-data>
        @csrf

        {{-- Six digits typed (or pasted from the keyboard suggestion) submit
        by themselves: nobody should have to click after the last digit. --}}
        <x-ui.input
            :label="__('security.challenge.label')"
            name="code"
            inputmode="numeric"
            maxlength="6"
            placeholder="000000"
            class="font-mono"
            autofocus
            autocomplete="one-time-code"
            x-on:input="/^\d{6}$/.test($event.target.value) && $event.target.form.requestSubmit()"
        />

        <div class="mt-2 flex items-center justify-between gap-4">
            <a
                href="{{ route('login') }}"
                class="text-brand font-semibold hover:underline"
                style="font-size: var(--text-sm)"
            >
                {{ __('security.challenge.back') }}
            </a>

            <x-ui.button type="submit" variant="primary">{{ __('security.challenge.cta') }}</x-ui.button>
        </div>
    </form>

    {{-- 30s cooldown before offering another code: gives the first mail
    time to arrive instead of inviting a double send, Slack-style. --}}
    <form
        method="POST"
        action="{{ route('device.challenge.resend') }}"
        class="mt-6 text-center"
        x-data="{ wait: 30 }"
        x-init="setInterval(() => (wait = Math.max(0, wait - 1)), 1000)"
    >
        @csrf
        <button
            type="submit"
            class="text-brand font-semibold hover:underline disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50"
            style="font-size: var(--text-sm)"
            x-bind:disabled="wait > 0"
        >
            {{ __('security.challenge.resend') }}
            <span x-show="wait > 0" x-cloak>(<span x-text="wait"></span>s)</span>
        </button>
    </form>

    <p class="text-muted mt-4 text-center" style="font-size: var(--text-xs)">{{ __('security.challenge.hint') }}</p>
</x-guest-layout>
