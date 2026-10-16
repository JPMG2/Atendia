<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('auth.confirm.heading') }}
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">{{ __('auth.confirm.intro') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.input
            :label="__('auth.fields.password')"
            name="password"
            type="password"
            icon="lock"
            placeholder="••••••••"
            required
            autofocus
            autocomplete="current-password"
        />

        <x-ui.button type="submit" variant="primary" size="lg" :fullWidth="true" class="mt-1">
            {{ __('auth.confirm.submit') }}
        </x-ui.button>
    </form>
</x-guest-layout>
