<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('auth.forgot.heading') }}
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">{{ __('auth.forgot.intro') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert variant="success" icon="message-circle" class="mb-6"> {{ session('status') }} </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.input
            :label="__('auth.fields.email')"
            name="email"
            type="email"
            icon="mail"
            :value="old('email')"
            :placeholder="__('auth.register.email_placeholder')"
            required
            autofocus
            autocomplete="username"
        />

        <x-ui.button type="submit" variant="primary" size="lg" :fullWidth="true" class="mt-1">
            {{ __('auth.forgot.submit') }}
        </x-ui.button>
    </form>
</x-guest-layout>
