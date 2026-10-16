<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('auth.verify.heading') }}
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">{{ __('auth.verify.intro') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert variant="success" icon="message-circle" class="mb-6"> {{ __('auth.verify.sent') }} </x-ui.alert>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-ui.button type="submit" variant="primary" size="lg" :fullWidth="true">
                {{ __('auth.verify.resend') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <x-ui.button type="submit" variant="ghost" size="lg" :fullWidth="true">
                {{ __('auth.verify.logout') }}
            </x-ui.button>
        </form>
    </div>
</x-guest-layout>
