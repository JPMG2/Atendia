<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('auth.reset.heading') }}
        </h2>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}" />

        <x-ui.input
            :label="__('auth.fields.email')"
            name="email"
            type="email"
            icon="mail"
            :value="old('email', $request->email)"
            required
            autofocus
            autocomplete="username"
        />

        <x-ui.new-password
            :label="__('auth.fields.password')"
            :placeholder="__('auth.register.password_placeholder')"
            required
        />

        <x-ui.input
            :label="__('auth.fields.password_confirmation')"
            name="password_confirmation"
            type="password"
            icon="lock"
            :placeholder="__('auth.register.confirm_placeholder')"
            required
            autocomplete="new-password"
        />

        <x-ui.button type="submit" variant="primary" size="lg" :fullWidth="true" class="mt-1">
            {{ __('auth.reset.submit') }}
        </x-ui.button>
    </form>
</x-guest-layout>
