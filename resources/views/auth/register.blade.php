<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-strong" style="font-weight:800; font-size:var(--text-3xl); letter-spacing:-0.02em;">
            Creá tu cuenta
        </h2>
        <p class="text-muted mt-1.5" style="font-size:var(--text-base);">
            Tres datos y tu asistente empieza a tomar forma.
        </p>
    </div>

    {{-- `novalidate` on purpose: the browser's native bubbles are the one
    dialog nobody can theme; the guard below speaks for the form instead,
    same criterion as the catalog masters. The server judges again. --}}
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5" novalidate
        x-data="registerGuard" x-on:submit="guard($event)">
        @csrf

        <x-ui.input
            :label="__('Name')"
            name="name"
            alpine-error="name"
            icon="users"
            :value="old('name')"
            placeholder="María Gómez"
            autofocus
            autocomplete="name"
        />

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            alpine-error="email"
            icon="mail"
            :value="old('email')"
            placeholder="vos@tunegocio.com"
            autocomplete="username"
        />

        <x-ui.input
            :label="__('Password')"
            name="password"
            type="password"
            alpine-error="password"
            icon="lock"
            placeholder="Mínimo 8 caracteres"
            autocomplete="new-password"
        />

        <x-ui.input
            :label="__('Confirm Password')"
            name="password_confirmation"
            type="password"
            alpine-error="password_confirmation"
            icon="lock"
            placeholder="La misma contraseña, para estar seguros"
            autocomplete="new-password"
        />

        <div class="flex items-center justify-between gap-4 mt-2">
            <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline" style="font-size:var(--text-sm);">
                {{ __('Already registered?') }}
            </a>

            <x-ui.button variant="primary">{{ __('Register') }}</x-ui.button>
        </div>
    </form>

    {{-- TEMPORARY shortcut while the wizard is a mock-up: jumps into the
    mock right after "the account was born", no typing. It goes away the day
    the real wizard replaces this screen. --}}
    <div class="flex justify-center mt-6">
        <x-ui.button variant="ghost" size="sm" href="{{ asset('maqueta-registro.html') }}#wizard">
            Ver cómo sigue el alta (maqueta temporal) →
        </x-ui.button>
    </div>

    <script>
        // Front mirror of RegisteredUserController's rules: what cannot pass
        // there is stopped here, before the request leaves.
        document.addEventListener('alpine:init', () => {
            Alpine.data('registerGuard', () => ({
                errors: {},

                guard(event) {
                    const values = Object.fromEntries(
                        ['name', 'email', 'password', 'password_confirmation']
                            .map(field => [field, event.target.elements[field]?.value ?? ''])
                    );

                    this.errors = validate(values, {
                        name: ['required', ['maxLength', 255], 'noMarkup'],
                        email: ['required', 'email', ['maxLength', 255]],
                        password: ['required', ['minLength', 8]],
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
