<x-guest-layout>
    <div class="mb-8">
        <h2
            class="text-strong font-display"
            style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
        >
            Creá tu cuenta
        </h2>
        <p class="text-muted mt-1.5" style="font-size: var(--text-base)">
            Tres datos y tu asistente empieza a tomar forma.
        </p>
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
            x-ref="emailInput"
            x-on:blur="mailHint = suggestEmail($event.target.value)"
            x-on:input="mailHint = ''"
        />

        <x-ui.email-suggest />

        <x-ui.input
            :label="__('Password')"
            name="password"
            type="password"
            alpine-error="password"
            icon="lock"
            placeholder="Ej. MiClave#2026"
            autocomplete="new-password"
            x-on:input="pw = $event.target.value"
        />

        {{-- The bar grows and warms as the checklist fills: one glance says
        how close the password is before reading a single rule. --}}
        <div class="pw-meter" x-bind:data-strength="pwStrength">
            <div class="pw-meter-track" aria-hidden="true">
                <div class="pw-meter-fill" x-bind:style="{ width: (pwScore / 4) * 100 + '%' }"></div>
            </div>
            <span
                class="pw-meter-label"
                x-text="pw === '' ? '' : pwScore <= 1 ? 'Débil' : pwScore < 4 ? 'Buena' : 'Fuerte'"
            ></span>
        </div>

        {{-- Each rule checks itself off as the password meets it: the
        pattern is taught by watching it happen, not by reading a hint. --}}
        <ul class="pw-rules" aria-live="polite">
            <li class="pw-rule" :class="{ 'is-ok': pw.length >= 8 }">
                <x-icon name="check" :size="14" />
                8 caracteres o más
            </li>
            <li class="pw-rule" :class="{ 'is-ok': /[A-ZÁÉÍÓÚÑÜ]/.test(pw) }">
                <x-icon name="check" :size="14" />
                Una letra mayúscula
            </li>
            <li class="pw-rule" :class="{ 'is-ok': /[0-9]/.test(pw) }">
                <x-icon name="check" :size="14" />
                Un número
            </li>
            <li class="pw-rule" :class="{ 'is-ok': /[^A-Za-z0-9ÁÉÍÓÚÑÜáéíóúñü]/.test(pw) }">
                <x-icon name="check" :size="14" />
                Un carácter especial (ej. ! @ #)
            </li>
        </ul>

        <x-ui.input
            :label="__('Confirm Password')"
            name="password_confirmation"
            type="password"
            alpine-error="password_confirmation"
            icon="lock"
            placeholder="La misma contraseña, para estar seguros"
            autocomplete="new-password"
        />

        <div class="mt-2 flex items-center justify-between gap-4">
            <a
                href="{{ route('login') }}"
                class="text-brand font-semibold hover:underline"
                style="font-size: var(--text-sm)"
            >
                {{ __('Already registered?') }}
            </a>

            {{-- type="submit" explicit: the component defaults to "button"
            (right for wire:click) and a default here is a dead form. --}}
            <x-ui.button type="submit" variant="primary">{{ __('Register') }}</x-ui.button>
        </div>
    </form>

    {{-- TEMPORARY, local only: jump into the wizard without creating an
    account (a demo client signs in). Deleted at go-live with its route. --}}
    @if (app()->environment('local'))
        <div class="mt-6 flex justify-center">
            <x-ui.button variant="ghost" size="sm" href="{{ route('onboarding.demo') }}">
                Ver el alta sin crear cuenta (atajo temporal) →
            </x-ui.button>
        </div>
    @endif

    <script>
        // Front mirror of RegisteredUserController's rules: what cannot pass
        // there is stopped here, before the request leaves.
        document.addEventListener('alpine:init', () => {
            Alpine.data('registerGuard', () => ({
                errors: {},

                // What the live rule checklist watches while the user types.
                pw: '',

                // What the x-ui.email-suggest button shows; form-guard's
                // global suggestEmail() fills it on blur.
                mailHint: '',

                // The four checklist rules folded into one score, so the
                // meter and the checklist can never disagree.
                get pwScore() {
                    return [
                        this.pw.length >= 8,
                        /[A-ZÁÉÍÓÚÑÜ]/.test(this.pw),
                        /[0-9]/.test(this.pw),
                        /[^A-Za-z0-9ÁÉÍÓÚÑÜáéíóúñü]/.test(this.pw),
                    ].filter(Boolean).length;
                },

                get pwStrength() {
                    return this.pwScore <= 1 ? 'weak' : this.pwScore < 4 ? 'good' : 'strong';
                },

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
