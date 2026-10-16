@props([
    'label',
    'name' => 'password',
    'placeholder' => null,
    'alpineError' => null,
])

{{-- The field for a password somebody is choosing: the input, the strength bar and the
four rules that check themselves off as they are met. The pattern is taught by watching
it happen, not by reading a hint. It keeps its own scope, so it works inside any form
and still reads the parent's `errors` when there is one. --}}
<div class="flex flex-col gap-5" x-data="passwordHelp">
    <x-ui.input
        :label="$label"
        :name="$name"
        type="password"
        icon="lock"
        :alpine-error="$alpineError"
        :placeholder="$placeholder"
        autocomplete="new-password"
        x-on:input="pw = $event.target.value"
        {{ $attributes->only(['required', 'autofocus']) }}
    />

    <div class="pw-meter" x-bind:data-strength="pwStrength">
        <div class="pw-meter-track" aria-hidden="true">
            <div class="pw-meter-fill" x-bind:style="{ width: (pwScore / 4) * 100 + '%' }"></div>
        </div>
        <span
            class="pw-meter-label"
            x-text="pw === '' ? '' : pwScore <= 1 ? @js(__('auth.password_rules.weak')) : pwScore < 4 ? @js(__('auth.password_rules.good')) : @js(__('auth.password_rules.strong'))"
        ></span>
    </div>

    <ul class="pw-rules" aria-live="polite">
        <li class="pw-rule" :class="{ 'is-ok': pw.length >= 8 }">
            <x-icon name="check" :size="14" />
            {{ __('auth.password_rules.length') }}
        </li>
        <li class="pw-rule" :class="{ 'is-ok': /[A-ZÁÉÍÓÚÑÜ]/.test(pw) }">
            <x-icon name="check" :size="14" />
            {{ __('auth.password_rules.upper') }}
        </li>
        <li class="pw-rule" :class="{ 'is-ok': /[0-9]/.test(pw) }">
            <x-icon name="check" :size="14" />
            {{ __('auth.password_rules.number') }}
        </li>
        <li class="pw-rule" :class="{ 'is-ok': /[^A-Za-z0-9ÁÉÍÓÚÑÜáéíóúñü]/.test(pw) }">
            <x-icon name="check" :size="14" />
            {{ __('auth.password_rules.symbol') }}
        </li>
    </ul>
</div>

@once
    <script>
        // The four rules folded into one score, so the bar and the checklist can never disagree.
        document.addEventListener('alpine:init', () => {
            Alpine.data('passwordHelp', () => ({
                pw: '',

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
            }));
        });
    </script>
@endonce
