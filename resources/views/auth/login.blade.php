<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            Hola de nuevo
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">
            Ingresá para seguir atendiendo con tu asistente.
        </p>
    </div>

    {{-- Landing spot of session-expired.js: a Livewire 419 redirects here
    with ?expired=1 (the full-page 419 already explained itself). --}}
    @if (request()->boolean('expired'))
        <x-ui.alert variant="info" icon="clock" class="mb-6"> {{ __('errors.expired_alert') }} </x-ui.alert>
    @endif

    {{-- Session status, such as the password-reset confirmation. --}}
    @if (session('status'))
        <x-ui.alert variant="success" icon="message-circle" class="mb-6"> {{ session('status') }} </x-ui.alert>
    @endif

    {{-- Same Mailcheck as the register: the typo that slipped in at
    sign-up comes straight back here. The form also remembers the last
    email used on this device, one typing less every day. --}}
    <form
        method="POST"
        action="{{ route('login') }}"
        class="flex flex-col gap-5"
        x-data="{
            mailHint: '',
            recallEmail() {
                try {
                    return localStorage.getItem('atendia-login-email') ?? '';
                } catch {
                    return '';
                }
            },
            rememberEmail(value) {
                try {
                    localStorage.setItem('atendia-login-email', value);
                } catch {}
            },
        }"
        x-on:submit="rememberEmail($refs.emailInput.value)"
    >
        @csrf

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            icon="mail"
            :value="old('email')"
            placeholder="vos@tunegocio.com"
            required
            autofocus
            autocomplete="username"
            x-ref="emailInput"
            x-init="$el.value = $el.value || recallEmail()"
            x-on:blur="mailHint = suggestEmail($event.target.value)"
            x-on:input="mailHint = ''"
        />

        <x-ui.email-suggest />

        <div>
            <x-ui.input
                label="Contraseña"
                name="password"
                type="password"
                icon="lock"
                placeholder="••••••••"
                required
                autocomplete="current-password"
            />

            @if (Route::has('password.request'))
                <div class="mt-2 flex justify-end">
                    <a
                        href="{{ route('password.request') }}"
                        class="text-brand font-semibold hover:underline"
                        style="font-size: var(--text-sm)"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            @endif
        </div>

        <x-ui.checkbox name="remember" label="Mantener la sesión iniciada" />

        <x-ui.button type="submit" variant="primary" size="lg" :fullWidth="true" class="mt-1"> Ingresar </x-ui.button>
    </form>

    @if (Route::has('register'))
        <p class="text-muted mt-8 text-center" style="font-size: var(--text-sm)">
            ¿Todavía no tenés cuenta?
            <a href="{{ route('register') }}" class="text-brand font-semibold hover:underline">Empezá gratis</a>
        </p>
    @endif
</x-guest-layout>
