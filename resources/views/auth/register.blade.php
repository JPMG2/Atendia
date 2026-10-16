<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            {{ __('auth.register.heading') }}
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">{{ __('auth.register.sub') }}</p>
    </div>

    {{-- `novalidate` on purpose: the browser's native bubbles are the one
    dialog nobody can theme; the guard below speaks for the form instead,
    same criterion as the catalog masters. The server judges again. --}}
    <form
        method="POST"
        action="{{ route('register') }}"
        class="flex flex-col gap-5"
        novalidate
        x-data="registerGuard"
        x-on:submit="guard($event)"
    >
        @csrf

        <x-ui.input
            :label="__('auth.fields.name')"
            name="name"
            alpine-error="name"
            icon="users"
            :value="old('name')"
            :placeholder="__('auth.register.name_placeholder')"
            autofocus
            autocomplete="name"
        />

        <x-ui.input
            :label="__('auth.fields.email')"
            name="email"
            type="email"
            alpine-error="email"
            icon="mail"
            :value="old('email')"
            :placeholder="__('auth.register.email_placeholder')"
            autocomplete="username"
            x-ref="emailInput"
            x-on:blur="mailHint = suggestEmail($event.target.value)"
            x-on:input="mailHint = ''"
        />

        <x-ui.email-suggest />

        <x-ui.new-password
            :label="__('auth.fields.password')"
            alpine-error="password"
            :placeholder="__('auth.register.password_placeholder')"
        />

        <x-ui.input
            :label="__('auth.fields.password_confirmation')"
            name="password_confirmation"
            type="password"
            alpine-error="password_confirmation"
            icon="lock"
            :placeholder="__('auth.register.confirm_placeholder')"
            autocomplete="new-password"
        />

        <div class="mt-2 flex items-center justify-between gap-4">
            <a
                href="{{ route('login') }}"
                class="text-brand font-semibold hover:underline"
                style="font-size: var(--text-sm)"
            >
                {{ __('auth.register.already') }}
            </a>

            {{-- type="submit" explicit: the component defaults to "button"
            (right for wire:click) and a default here is a dead form. --}}
            <x-ui.button type="submit" variant="primary">{{ __('auth.register.submit') }}</x-ui.button>
        </div>
    </form>

    <script>
        // Front mirror of RegisteredUserController's rules: what cannot pass
        // there is stopped here, before the request leaves.
        document.addEventListener('alpine:init', () => {
            Alpine.data('registerGuard', () => ({
                errors: {},

                // What the x-ui.email-suggest button shows; form-guard's
                // global suggestEmail() fills it on blur.
                mailHint: '',

                guard(event) {
                    const values = Object.fromEntries(
                        ['name', 'email', 'password', 'password_confirmation'].map((field) => [
                            field,
                            event.target.elements[field]?.value ?? '',
                        ]),
                    );

                    this.errors = validate(values, {
                        name: ['required', ['maxLength', 255], 'noMarkup'],
                        email: ['required', 'email', ['maxLength', 255]],
                        password: ['required', ['minLength', 8], 'hasUpper', 'hasNumber', 'hasSymbol'],
                        password_confirmation: ['required', ['same', values.password]],
                    });

                    if (Object.keys(this.errors).length > 0) {
                        event.preventDefault();
                    }
                },
            }));
        });
    </script>
</x-guest-layout>
